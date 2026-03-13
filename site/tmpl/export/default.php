<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

@ob_end_clean();

use CB\Component\Contentbuilderng\Administrator\Helper\VendorHelper;

VendorHelper::load();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Joomla\CMS\Factory;

//Font::setAutoSizeMethod(Font::AUTOSIZE_METHOD_EXACT);

$db = Factory::getContainer()->get(DatabaseInterface::class);
/** @var \Joomla\CMS\Application\CMSApplication $app */
$app = Factory::getApplication();
$input = $app->input;

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
if ($this->data->show_id_column) {
    $col_id = ++$colreserved;
    array_push($reserved_labels, Text::_('COM_CONTENTBUILDERNG_ID'));
}

// Case of state true -> column reserved.
$col_state = 0;
if ($this->data->list_state) {
    $col_state = ++$colreserved;
    array_push($reserved_labels, Text::_('COM_CONTENTBUILDERNG_EDIT_STATE'));
}

// Case of publish true -> column reserved.
$col_publish = 0;
if ($this->data->list_publish) {
    $col_publish = ++$colreserved;
    array_push($reserved_labels, Text::_('COM_CONTENTBUILDERNG_PUBLISH'));
}

$labels = array_merge($reserved_labels, $labels);

$col = 1;
foreach ($labels as $label) {
    $cell = [$col++, 1];
    $worksheet1->setCellValue($cell, $label);
    $worksheet1->getStyle($cell)->getFont()->setBold(true);
}

// 2 -- Data.
$row = 2;
foreach ((array) ($this->data->items ?? []) as $item) {
    $i = 1; // Colonne de départ
    
    // Si on veut mettre l'ID
    if ($col_id > 0) {
        $worksheet1->setCellValue([$i++, $row], $item->colRecord);
    }

    // Si on veut mettre la colonne d'état.
    if ($col_state > 0) {
        // Sécuriser la requête
        $recordId = $db->quote($item->colRecord);
        $sql = "SELECT title, color 
                FROM `#__contentbuilderng_list_states` 
                WHERE id = (SELECT state_id 
                            FROM `#__contentbuilderng_list_records` 
                            WHERE record_id = $recordId)";
        $db->setQuery($sql);
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
            $worksheet1->setCellValue([$i++, $row], $result[0]);
        }
        else {
            $i++;
        }
    }

    // Si on veut mettre la colonne d'état.
    if ($col_publish > 0) {
        $i++;
    }
 
    // Les autres colonnes.
    foreach ((array) ($this->data->visible_cols ?? []) as $id) {
        $worksheet1->setCellValue([$i++, $row], $item->{"col$id"});          
    }

    $row++; // Passer à la ligne suivante pour chaque item
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
$date = Factory::getDate('now', $userTimezone);

$filenameTitle = $rawSheetTitle;
if ($filenameTitle === '' && !empty($this->data->type) && $this->data->type === 'com_breezingforms') {
    $query = $db->getQuery(true)
        ->select($db->quoteName('name'))
        ->from($db->quoteName('#__facileforms_forms'))
        ->where($db->quoteName('id') . ' = ' . (int) $this->data->reference_id);
    $db->setQuery($query);
    $filenameTitle = (string) ($db->loadResult() ?: '');
}

if ($filenameTitle === '') {
    $filenameTitle = 'Export';
}

$safeFilenameTitle = preg_replace('/[^\pL\pN _.-]+/u', '_', $filenameTitle);
$safeFilenameTitle = trim((string) preg_replace('/\s+/u', ' ', (string) $safeFilenameTitle));
if ($safeFilenameTitle === '') {
    $safeFilenameTitle = 'Export';
}

$filename = "CB_export_" . $safeFilenameTitle . '_' . $date->format('Y-m-d_Hi', true) . ".xlsx";


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
header('Content-Disposition: attachment; filename=' . $filename);
header("Content-Transfer-Encoding: binary ");

ob_end_clean();
ob_start();



$objWriter = new Xlsx($spreadsheet);
$objWriter->save('php://output');

exit;
