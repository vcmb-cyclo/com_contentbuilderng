<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

class MenuService
{
    public function __construct(
        private readonly DatabaseInterface $db
    ) {
    }

    public function createBackendMenuItem($contentbuilderngFormId, $name, $update): void
    {
        $this->createBackendMenuItem3($contentbuilderngFormId, $name, $update);
    }

    public function createBackendMenuItem15($contentbuilderngFormId, $name, $update): void
    {
        $db = $this->db;
        $parentId = 0;

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__components'))
            ->where([
                $db->quoteName('option') . ' = ' . $db->quote(''),
                $db->quoteName('admin_menu_link') . ' = ' . $db->quote('option=com_contentbuilderng&viewcontainer=true'),
            ]);
        $db->setQuery($query);
        $res = $db->loadResult();

        if ($res) {
            $parentId = $res;
        } else {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__components'))
                ->columns($db->quoteName([
                    'name',
                    'admin_menu_link',
                    'admin_menu_alt',
                    'option',
                    'admin_menu_img',
                    'iscore',
                ]))
                ->values(implode(',', [
                    $db->quote('ContentBuilder NG Views'),
                    $db->quote('option=com_contentbuilderng&viewcontainer=true'),
                    $db->quote('contentbuilderng'),
                    $db->quote(''),
                    $db->quote('media/com_contentbuilderng/images/logo_icon_cb.png'),
                    1,
                ]));
            $db->setQuery($query);
            $db->execute();
            $parentId = $db->insertid();
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__components'))
            ->where(
                $db->quoteName('admin_menu_link') . ' = ' . $db->quote('option=com_contentbuilderng&task=list.display&id=' . (int) $contentbuilderngFormId)
            );
        $db->setQuery($query);
        $menuitem = $db->loadResult();

        if (!$update) {
            return;
        }

        $query = $db->getQuery(true)
            ->select('count(' . $db->quoteName('published') . ')')
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . (int) $contentbuilderngFormId);
        $db->setQuery($query);

        if ($db->loadResult()) {
            if (!$menuitem) {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__components'))
                    ->columns($db->quoteName([
                        'name',
                        'admin_menu_link',
                        'admin_menu_alt',
                        'option',
                        'admin_menu_img',
                        'iscore',
                        'parent',
                    ]))
                    ->values(implode(',', [
                        $db->quote($name),
                        $db->quote('option=com_contentbuilderng&task=list.display&id=' . (int) $contentbuilderngFormId),
                        $db->quote($name),
                        $db->quote('com_contentbuilderng'),
                        $db->quote('media/com_contentbuilderng/images/logo_icon_cb.png'),
                        1,
                        (int) $parentId,
                    ]));
            } else {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__components'))
                    ->set([
                        $db->quoteName('name') . ' = ' . $db->quote($name),
                        $db->quoteName('admin_menu_alt') . ' = ' . $db->quote($name),
                        $db->quoteName('parent') . ' = ' . (int) $parentId,
                    ])
                    ->where($db->quoteName('id') . ' = ' . (int) $menuitem);
            }
            $db->setQuery($query);
            $db->execute();
        }
    }

    public function createBackendMenuItem16($contentbuilderngFormId, $name, $update): void
    {
        if (!trim((string) $name)) {
            return;
        }

        $db = $this->db;

        $query = $db->getQuery(true)
            ->select($db->quoteName('component_id'))
            ->from($db->quoteName('#__menu'))
            ->where([
                $db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilderng'),
                $db->quoteName('parent_id') . ' = ' . 1,
            ]);
        $db->setQuery($query);
        $result = $db->loadResult();

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where([
                $db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilderng&viewcontainer=true'),
                $db->quoteName('parent_id') . ' = ' . 1,
            ]);
        $db->setQuery($query);
        $oldId = $db->loadResult();
        $parentId = $oldId;

        if (!$oldId) {
            // lft/rgt use subqueries in VALUES — kept as raw string per DDL/complex-subquery exception
            $db->setQuery(
                "insert into #__menu (
                    `title`, alias, menutype, parent_id,
                    link,
                    ordering, level, component_id, client_id, img, lft,rgt
                )
                values (
                    'ContentBuilder NG Views', 'ContentBuilder NG Views', 'main', 1,
                    'index.php?option=com_contentbuilderng&viewcontainer=true',
                    '0', 1, " . (int) $result . ", 1, 'media/com_contentbuilderng/images/logo_icon_cb.png',( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet )
                )"
            );
            $db->execute();
            $parentId = $db->insertid();

            $query = $db->getQuery(true)
                ->select('max(' . $db->quoteName('mrgt') . '.' . $db->quoteName('rgt') . ')+1')
                ->from($db->quoteName('#__menu', 'mrgt'));
            $db->setQuery($query);
            $rgt = $db->loadResult();

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set($db->quoteName('rgt') . ' = ' . (int) $rgt)
                ->where([
                    $db->quoteName('title') . ' = ' . $db->quote('Menu_Item_Root'),
                    $db->quoteName('alias') . ' = ' . $db->quote('root'),
                ]);
            $db->setQuery($query);
            $db->execute();
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where(
                $db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilderng&task=list.display&id=' . (int) $contentbuilderngFormId)
            );
        $db->setQuery($query);
        $menuitem = $db->loadResult();

        if (!$update) {
            return;
        }
        if (!$result) {
            die("ContentBuilder main menu item not found!");
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where([
                $db->quoteName('alias') . ' = ' . $db->quote($name),
                $db->quoteName('link') . ' LIKE ' . $db->quote('index.php?option=com_contentbuilderng&task=list.display&id=%'),
                $db->quoteName('link') . ' <> ' . $db->quote('index.php?option=com_contentbuilderng&task=list.display&id=' . (int) $contentbuilderngFormId),
            ]);
        $db->setQuery($query);
        $nameExists = $db->loadResult();

        if ($nameExists) {
            $name .= '_';
        }

        if (!$menuitem) {
            // lft/rgt use subqueries in VALUES — kept as raw string per DDL/complex-subquery exception
            $db->setQuery(
                "insert into #__menu (
                    `title`, alias, menutype, parent_id,
                    link,
                    ordering, level, component_id, client_id, img,lft,rgt
                )
                values (
                    " . $db->quote($name) . ", " . $db->quote($name) . ", 'main', " . (int) $parentId . ",
                    'index.php?option=com_contentbuilderng&task=list.display&id=" . (int) $contentbuilderngFormId . "',
                    '0', 1, " . (int) $result . ", 1, 'media/com_contentbuilderng/images/logo_icon_cb.png',
                    ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone), ( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet)
                )"
            );
            $db->execute();

            $query = $db->getQuery(true)
                ->select('max(' . $db->quoteName('mrgt') . '.' . $db->quoteName('rgt') . ')+1')
                ->from($db->quoteName('#__menu', 'mrgt'));
            $db->setQuery($query);
            $rgt = $db->loadResult();

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set($db->quoteName('rgt') . ' = ' . (int) $rgt)
                ->where([
                    $db->quoteName('title') . ' = ' . $db->quote('Menu_Item_Root'),
                    $db->quoteName('alias') . ' = ' . $db->quote('root'),
                ]);
            $db->setQuery($query);
            $db->execute();
        } else {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set([
                    $db->quoteName('title') . ' = ' . $db->quote($name),
                    $db->quoteName('alias') . ' = ' . $db->quote($name),
                    $db->quoteName('parent_id') . ' = ' . (int) $parentId,
                ])
                ->where($db->quoteName('id') . ' = ' . (int) $menuitem);
            $db->setQuery($query);
            $db->execute();
        }
    }

    public function createBackendMenuItem3($contentbuilderngFormId, $name, $update): void
    {
        if (!trim((string) $name)) {
            return;
        }

        $db = $this->db;

        $query = $db->getQuery(true)
            ->select($db->quoteName('component_id'))
            ->from($db->quoteName('#__menu'))
            ->where([
                $db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilderng'),
                $db->quoteName('parent_id') . ' = ' . 1,
            ]);
        $db->setQuery($query);
        $result = $db->loadResult();

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where([
                $db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilderng&viewcontainer=true'),
                $db->quoteName('parent_id') . ' = ' . 1,
            ]);
        $db->setQuery($query);
        $oldId = $db->loadResult();
        $parentId = $oldId;

        if (!$oldId) {
            // lft/rgt use subqueries in VALUES — kept as raw string per DDL/complex-subquery exception
            $db->setQuery(
                "insert into #__menu (
                    `title`, alias, menutype, type, parent_id,
                    link,
                    level, component_id, client_id, img, lft,rgt
                )
                values (
                    'ContentBuilder NG Views', 'ContentBuilder NG Views', 'main', 'component', 1,
                    'index.php?option=com_contentbuilderng&viewcontainer=true',
                    1, " . (int) $result . ", 1, 'media/com_contentbuilderng/images/logo_icon_cb.png',( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone ),( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet )
                )"
            );
            $db->execute();
            $parentId = $db->insertid();

            $query = $db->getQuery(true)
                ->select('max(' . $db->quoteName('mrgt') . '.' . $db->quoteName('rgt') . ')+1')
                ->from($db->quoteName('#__menu', 'mrgt'));
            $db->setQuery($query);
            $rgt = $db->loadResult();

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set($db->quoteName('rgt') . ' = ' . (int) $rgt)
                ->where([
                    $db->quoteName('title') . ' = ' . $db->quote('Menu_Item_Root'),
                    $db->quoteName('alias') . ' = ' . $db->quote('root'),
                ]);
            $db->setQuery($query);
            $db->execute();
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where(
                $db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_contentbuilderng&task=list.display&id=' . (int) $contentbuilderngFormId)
            );
        $db->setQuery($query);
        $menuitem = $db->loadResult();

        if (!$update) {
            return;
        }
        if (!$result) {
            die("ContentBuilder NG main menu item not found!");
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where([
                $db->quoteName('alias') . ' = ' . $db->quote($name),
                $db->quoteName('link') . ' LIKE ' . $db->quote('index.php?option=com_contentbuilderng&task=list.display&id=%'),
                $db->quoteName('link') . ' <> ' . $db->quote('index.php?option=com_contentbuilderng&task=list.display&id=' . (int) $contentbuilderngFormId),
            ]);
        $db->setQuery($query);
        $nameExists = $db->loadResult();

        if ($nameExists) {
            $name .= '_';
        }

        if (!$menuitem) {
            // lft/rgt use subqueries in VALUES — kept as raw string per DDL/complex-subquery exception
            $db->setQuery(
                "insert into #__menu (
                    params,`path`,`title`, alias, menutype, type, parent_id,
                    link,
                    level, component_id, client_id, img,lft,rgt
                )
                values (
                    '',''," . $db->quote($name) . ", " . $db->quote($name) . ", 'main', 'component', " . (int) $parentId . ",
                    'index.php?option=com_contentbuilderng&task=list.display&id=" . (int) $contentbuilderngFormId . "',
                    1, " . (int) $result . ", 1, 'media/com_contentbuilderng/images/logo_icon_cb.png',
                    ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone), ( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet)
                )"
            );
            $db->execute();

            $query = $db->getQuery(true)
                ->select('max(' . $db->quoteName('mrgt') . '.' . $db->quoteName('rgt') . ')+1')
                ->from($db->quoteName('#__menu', 'mrgt'));
            $db->setQuery($query);
            $rgt = $db->loadResult();

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set($db->quoteName('rgt') . ' = ' . (int) $rgt)
                ->where([
                    $db->quoteName('title') . ' = ' . $db->quote('Menu_Item_Root'),
                    $db->quoteName('alias') . ' = ' . $db->quote('root'),
                ]);
            $db->setQuery($query);
            $db->execute();
        } else {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set([
                    $db->quoteName('title') . ' = ' . $db->quote($name),
                    $db->quoteName('alias') . ' = ' . $db->quote($name),
                    $db->quoteName('parent_id') . ' = ' . (int) $parentId,
                ])
                ->where($db->quoteName('id') . ' = ' . (int) $menuitem);
            $db->setQuery($query);
            $db->execute();
        }
    }
}
