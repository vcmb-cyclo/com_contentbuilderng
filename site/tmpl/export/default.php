<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */


// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Site\Helper\SpreadsheetExportValueHelper;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

@ob_end_clean();

use CB\Component\Contentbuilderng\Administrator\Helper\VendorHelper;

VendorHelper::load();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Joomla\CMS\Factory;
use Joomla\CMS\Date\Date;
use CB\Component\Contentbuilderng\Site\Service\ExportFilenameService;

//Font::setAutoSizeMethod(Font::AUTOSIZE_METHOD_EXACT);

$db = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getDatabase();
/** @var \Joomla\CMS\Application\CMSApplication $app */
$app = \CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper::getApplication();
$input = $app->getInput();

// Pourcentage de mélange de la couleur d'état vers le blanc (0-100), paramétrable via la requête.
$stateColorMixPercent = (float) $input->get('state_color_mix_percent', 75, 'float');
$stateColorMixPercent = max(0.0, min(100.0, $stateColorMixPercent));
$stateColorMixRatio = $stateColorMixPercent / 100.0;

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()->setCreator("ContentBuilderng")->setLastModifiedBy("ContentBuilderng");

// Create "Sheet 1" tab as the first worksheet.
// https://phpspreadsheet.readthedocs.io/en/latest/topics/worksheets/adding-a-new-worksheet
$spreadsheet->removeSheetByIndex(0);

// Spreadsheet sheet title must be non-empty and cannot contain []:*?/\ characters.
$rawSheetTitle = '';
if (!empty($this->data->name)) {
    $rawSheetTitle = (string) $this->data->name;
} elseif (!empty($this->data->title)) {
    $rawSheetTitle = (string) $this->data->title;
} elseif (!empty($this->data->form) && method_exists($this->data->form, 'getPageTitle')) {
    $rawSheetTitle = (string) $this->data->form->getPageTitle();
}
$sheetTitle = preg_replace('/[\x00-\x1F\x7F\[\]\:\*\?\/\\\\]/u', ' ', $rawSheetTitle);
$sheetTitle = trim((string) preg_replace('/\s+/u', ' ', (string) $sheetTitle));
if ($sheetTitle === '') {
    $sheetTitle = 'Export';
}
if (function_exists('mb_substr')) {
    $sheetTitle = (string) mb_substr($sheetTitle, 0, 31);
} else {
    $sheetTitle = (string) substr($sheetTitle, 0, 31);
}
if ($sheetTitle === '') {
    $sheetTitle = 'Export';
}

$worksheet1 = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, $sheetTitle);
$spreadsheet->addSheet($worksheet1, 0);

// LETTER -> A4.
$worksheet1->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

// Freeze first line.
$worksheet1->freezePane('A2');

// First row in grey.
// Appliquer le style à la première ligne
$style = $worksheet1->getStyle('1:1');

// Fond gris
$style->getFill()
    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
    ->getStartColor()
    ->setARGB('c0c0c0');

// Centrage horizontal et vertical
$style->getAlignment()
    ->setHorizontal(PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
    ->setVertical(PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

// 1 -- Labels.
$labels = is_array($this->data->visible_labels ?? null) ? $this->data->visible_labels : [];
$colreserved = 0;

// Case of show_id_column true -> First column reserved.
$col_id = 0;
$reserved_labels = [];
if ($this->data->export_id_column) {
    $col_id = ++$colreserved;
    array_push($reserved_labels, Text::_('COM_CONTENTBUILDERNG_ID'));
}

// Case of state true -> column reserved.
$col_state = 0;
if ($this->data->export_state_column) {
    $col_state = ++$colreserved;
    array_push($reserved_labels, Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'));
}

// Case of publish true -> column reserved.
$col_publish = 0;
if ($this->data->export_publish_column) {
    $col_publish = ++$colreserved;
    array_push($reserved_labels, Text::_('COM_CONTENTBUILDERNG_PUBLISH'));
}

$labels = array_merge($reserved_labels, $labels);

$col = 1;
foreach ($labels as $label) {
    $cell = [$col++, 1];
    // Always write as an explicit string: a value starting with "=", "+", "-"
    // or "@" would otherwise be stored as a formula and executed by the
    // spreadsheet application opening the export (CSV/XLSX injection).
    $worksheet1->setCellValueExplicit($cell, (string) $label, DataType::TYPE_STRING);
    $worksheet1->getStyle($cell)->getFont()->setBold(true);
}

$visibleColumns = array_values((array) ($this->data->visible_cols ?? []));
$exportItems = array_values((array) ($this->data->items ?? []));
$exportOrderTypes = (array) ($this->data->export_order_types ?? []);
$exportSourceTypes = (array) ($this->data->export_source_types ?? []);
$resolvedColumnTypes = [];
foreach ($visibleColumns as $id) {
    $columnKey = 'col' . $id;
    $values = array_map(
        static fn(object $item): mixed => $item->{$columnKey} ?? '',
        $exportItems
    );
    $resolvedColumnTypes[(string) $id] = SpreadsheetExportValueHelper::resolveColumnType(
        $values,
        (string) ($exportOrderTypes[$columnKey] ?? ''),
        (string) ($exportSourceTypes[$columnKey] ?? '')
    );
}

// 2 -- Data.
$row = 2;
foreach ($exportItems as $item) {
    $i = 1; // Colonne de départ
    
    // Si on veut mettre l'ID
    if ($col_id > 0) {
        $worksheet1->setCellValue([$i++, $row], $item->colRecord);
    }

    // Si on veut mettre la colonne d'état.
    if ($col_state > 0) {
        $stateQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('states.title'),
                $db->quoteName('states.color'),
            ])
            ->from($db->quoteName('#__contentbuilderng_list_records', 'records'))
            ->join(
                'INNER',
                $db->quoteName('#__contentbuilderng_list_states', 'states')
                . ' ON ' . $db->quoteName('states.id') . ' = ' . $db->quoteName('records.state_id')
            )
            ->where($db->quoteName('records.form_id') . ' = ' . (int) $this->data->id)
            ->where($db->quoteName('records.record_id') . ' = ' . (int) $item->colRecord)
            ->where($db->quoteName('states.form_id') . ' = ' . (int) $this->data->id);
        $db->setQuery($stateQuery, 0, 1);
        $result = $db->loadRow();

        if ($result !== null) {
            if (empty($result[1]) || !preg_match('/^[0-9A-F]{6}$/i', $result[1])) {
                $result[1] = 'FFFFFF'; // Blanc par défaut
            }

            // Convertir $i en lettre de colonne
            $columnLetter = Coordinate::stringFromColumnIndex($i);
            $cell = $columnLetter . $row; // Ex. 'B2'

            // Éclaircir la couleur d'état selon le pourcentage configuré vers le blanc pour l'export.
            if ($result[1] !== 'FFFFFF') { // !== pour cohérence avec chaînes
                $baseColor = strtoupper($result[1]);
                $lightColor = '';

                for ($channelIndex = 0; $channelIndex < 3; $channelIndex++) {
                    $channel = hexdec(substr($baseColor, $channelIndex * 2, 2));
                    $lightChannel = (int) round($channel + ((255 - $channel) * $stateColorMixRatio));
                    $lightColor .= strtoupper(str_pad(dechex($lightChannel), 2, '0', STR_PAD_LEFT));
                }

                $worksheet1->getStyle($cell)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $lightColor]
                    ]
                ]);
            }
            $worksheet1->setCellValueExplicit([$i++, $row], (string) $result[0], DataType::TYPE_STRING);
        }
        else {
            $i++;
        }
    }

    // Si on veut mettre la colonne d'état.
    if ($col_publish > 0) {
        $publishedValue = isset($item->colPublished) ? (int) $item->colPublished : null;
        if ($publishedValue === null && isset($this->data->published_items[$item->colRecord])) {
            $publishedValue = (int) $this->data->published_items[$item->colRecord];
        }

        $worksheet1->setCellValue(
            [$i++, $row],
            $publishedValue === 1 ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED')
        );
    }
 
    // Les autres colonnes.
    foreach ($visibleColumns as $id) {
        $value = $item->{"col$id"} ?? '';
        $columnType = $resolvedColumnTypes[(string) $id] ?? SpreadsheetExportValueHelper::TEXT;
        $preparedValue = SpreadsheetExportValueHelper::prepareCellValue($value, $columnType);
        $cell = Coordinate::stringFromColumnIndex($i++) . $row;
        if ($preparedValue['value'] instanceof \DateTimeImmutable) {
            $spreadsheetValue = $preparedValue['type'] === SpreadsheetExportValueHelper::TIME
                ? (((int) $preparedValue['value']->format('H') * 3600)
                    + ((int) $preparedValue['value']->format('i') * 60)
                    + (int) $preparedValue['value']->format('s')) / 86400
                : SpreadsheetDate::dateTimeToExcel($preparedValue['value']);
            $worksheet1->setCellValueExplicit(
                $cell,
                $spreadsheetValue,
                DataType::TYPE_NUMERIC
            );
        } elseif (in_array($preparedValue['type'], [
            SpreadsheetExportValueHelper::INTEGER,
            SpreadsheetExportValueHelper::DECIMAL,
        ], true)) {
            $worksheet1->setCellValueExplicit($cell, $preparedValue['value'], DataType::TYPE_NUMERIC);
        } else {
            $worksheet1->setCellValueExplicit($cell, (string) $preparedValue['value'], DataType::TYPE_STRING);
            if ($preparedValue['ignoreNumberStoredAsText']) {
                $worksheet1->getCell($cell)->getIgnoredErrors()->setNumberStoredAsText(true);
            }
        }

        $numberFormat = SpreadsheetExportValueHelper::numberFormat($columnType);
        if ($numberFormat !== null) {
            $worksheet1->getStyle($cell)->getNumberFormat()->setFormatCode($numberFormat);
        }
    }

    $row++; // Passer à la ligne suivante pour chaque item
}

$lastDataRow = $row - 1;
if ($lastDataRow >= 2) {
    foreach ($visibleColumns as $columnOffset => $id) {
        $columnIndex = $colreserved + $columnOffset + 1;
        $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
        $columnType = $resolvedColumnTypes[(string) $id] ?? SpreadsheetExportValueHelper::TEXT;
        $worksheet1->getStyle($columnLetter . '2:' . $columnLetter . $lastDataRow)
            ->getAlignment()
            ->setHorizontal($columnType === SpreadsheetExportValueHelper::TEXT
                ? Alignment::HORIZONTAL_LEFT
                : Alignment::HORIZONTAL_RIGHT);
    }
}

$spreadsheet->getDefaultStyle()->getAlignment()->setWrapText(true);
//$worksheet1->setTitle("export-" . date('Y-m-d_Hi') . ".xlsx");

// Name file.
// Récupérer le fuseau horaire du client (via POST, GET, ou autre)
$userTimezone = $input->get('user_timezone', null, 'string');

// Si aucun fuseau horaire client n'est fourni, utiliser celui de Joomla
if (!$userTimezone) {
    $userTimezone = (string) $app->get('offset', 'UTC');
}

// Créer la date avec le fuseau horaire
$date = (new Date('now', $userTimezone));

$filenameTitle = $rawSheetTitle;
if ($filenameTitle === '' && !empty($this->data->type) && $this->data->type === 'com_breezingformsng') {
    $query = $db->getQuery(true)
        ->select($db->quoteName('name'))
        ->from($db->quoteName('#__facileforms_forms'))
        ->where($db->quoteName('id') . ' = ' . (int) $this->data->reference_id);
    $db->setQuery($query);
    $filenameTitle = (string) ($db->loadResult() ?: '');
}

$filename = ExportFilenameService::build(
    $filenameTitle,
    $input->getCmd('cb_export_filename_mode', ExportFilenameService::MODE_DEFAULT),
    $input->getString('cb_export_filename', ''),
    $date->format('Y-m-d_Hi', true)
);


$spreadsheet->setActiveSheetIndex(0);

foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
    // Active l'auto-size pour toutes les colonnes qui contiennent des données
    foreach ($worksheet->getColumnIterator() as $column) {
        $worksheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
    }

    // Force le calcul des largeurs basées sur le contenu réel
    $worksheet->calculateColumnWidths();

    // Applique un plafond de 70 caractères de largeur
    foreach ($worksheet->getColumnIterator() as $column) {
        $dimension = $worksheet->getColumnDimension($column->getColumnIndex());

        if ($dimension->getWidth() > 70) {
            $dimension->setAutoSize(false);
            $dimension->setWidth(70);
        }
    }
}


header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
;
header('Cache-Control: max-age=0');
$asciiFilename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'export.xlsx';
header(
    'Content-Disposition: attachment; filename="' . $asciiFilename
    . '"; filename*=UTF-8\'\'' . rawurlencode($filename)
);
header("Content-Transfer-Encoding: binary ");

ob_end_clean();
ob_start();



$objWriter = new Xlsx($spreadsheet);
$objWriter->save('php://output');

exit;
