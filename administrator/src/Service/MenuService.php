<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class MenuService
{
    public function createBackendMenuItem($contentbuilderngFormId, $name, $update): void
    {
        $this->createBackendMenuItem3($contentbuilderngFormId, $name, $update);
    }

    public function createBackendMenuItem15($contentbuilderngFormId, $name, $update): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $parentId = 0;
        $db->setQuery("Select id From #__components Where `option`='' And admin_menu_link='option=com_contentbuilderng&viewcontainer=true'");
        $res = $db->loadResult();
        if ($res) {
            $parentId = $res;
        } else {
            $db->setQuery(
                "Insert Into #__components
                 (
                    `name`,
                    `admin_menu_link`,
                    `admin_menu_alt`,
                    `option`,
                    `admin_menu_img`,
                    `iscore`
                 )
                 Values
                 (
                    'ContentBuilder NG Views',
                    'option=com_contentbuilderng&viewcontainer=true',
                    'contentbuilderng',
                    '',
                    'media/com_contentbuilderng/images/logo_icon_cb.png',
                    1
                 )"
            );
            $db->execute();
            $parentId = $db->insertid();
        }

        $db->setQuery("Select id From #__components Where admin_menu_link = 'option=com_contentbuilderng&task=list.display&id=" . (int) $contentbuilderngFormId . "'");
        $menuitem = $db->loadResult();
        if (!$update) {
            return;
        }

        $db->setQuery("Select count(published) From #__contentbuilderng_elements Where form_id = " . (int) $contentbuilderngFormId);
        if ($db->loadResult()) {
            if (!$menuitem) {
                $db->setQuery(
                    "Insert Into #__components
                     (
                        `name`,
                        `admin_menu_link`,
                        `admin_menu_alt`,
                        `option`,
                        `admin_menu_img`,
                        `iscore`,
                        `parent`
                     )
                     Values
                     (
                        " . $db->quote($name) . ",
                        'option=com_contentbuilderng&task=list.display&id=" . (int) $contentbuilderngFormId . "',
                        " . $db->quote($name) . ",
                        'com_contentbuilderng',
                        'media/com_contentbuilderng/images/logo_icon_cb.png',
                        1,
                        '$parentId'
                     )"
                );
            } else {
                $db->setQuery(
                    "Update #__components
                     Set
                     `name` = " . $db->quote($name) . ",
                     `admin_menu_alt` = " . $db->quote($name) . ",
                     `parent` = $parentId
                     Where id = $menuitem"
                );
            }
            $db->execute();
        }
    }

    public function createBackendMenuItem16($contentbuilderngFormId, $name, $update): void
    {
        if (!trim((string) $name)) {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $db->setQuery("Select component_id From #__menu Where `link`='index.php?option=com_contentbuilderng' And parent_id = 1");
        $result = $db->loadResult();

        $db->setQuery("Select id From #__menu Where `link`='index.php?option=com_contentbuilderng&viewcontainer=true' And parent_id = 1");
        $oldId = $db->loadResult();
        $parentId = $oldId;

        if (!$oldId) {
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

            $db->setQuery("Select max(mrgt.rgt)+1 From #__menu As mrgt");
            $rgt = $db->loadResult();

            $db->setQuery("Update `#__menu` Set rgt = " . $rgt . " Where `title` = 'Menu_Item_Root' And `alias` = 'root'");
            $db->execute();
        }

        $db->setQuery("Select id From #__menu Where link = 'index.php?option=com_contentbuilderng&task=list.display&id=" . (int) $contentbuilderngFormId . "'");
        $menuitem = $db->loadResult();

        if (!$update) {
            return;
        }
        if (!$result) {
            die("ContentBuilder main menu item not found!");
        }

        $db->setQuery("Select id From #__menu Where alias = " . $db->quote($name) . " And link Like 'index.php?option=com_contentbuilderng&task=list.display&id=%' And link <> 'index.php?option=com_contentbuilderng&task=list.display&id=" . (int) $contentbuilderngFormId . "'");
        $nameExists = $db->loadResult();

        if ($nameExists) {
            $name .= '_';
        }

        if (!$menuitem) {
            $db->setQuery(
                "insert into #__menu (
                    `title`, alias, menutype, parent_id,
                    link,
                    ordering, level, component_id, client_id, img,lft,rgt
                )
                values (
                    " . $db->quote($name) . ", " . $db->quote($name) . ", 'main', '$parentId',
                    'index.php?option=com_contentbuilderng&task=list.display&id=" . (int) $contentbuilderngFormId . "',
                    '0', 1, " . (int) $result . ", 1, 'media/com_contentbuilderng/images/logo_icon_cb.png',
                    ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone), ( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet)
                )"
            );
            $db->execute();

            $db->setQuery("Select max(mrgt.rgt)+1 From #__menu As mrgt");
            $rgt = $db->loadResult();

            $db->setQuery("Update `#__menu` Set rgt = " . $rgt . " Where `title` = 'Menu_Item_Root' And `alias` = 'root'");
            $db->execute();
        } else {
            $db->setQuery(
                "Update #__menu Set `title` = " . $db->quote($name) . ", alias = " . $db->quote($name) . ", `parent_id` = '$parentId' Where id = $menuitem"
            );
            $db->execute();
        }
    }

    public function createBackendMenuItem3($contentbuilderngFormId, $name, $update): void
    {
        if (!trim((string) $name)) {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $db->setQuery("Select component_id From #__menu Where `link`='index.php?option=com_contentbuilderng' And parent_id = 1");
        $result = $db->loadResult();

        $db->setQuery("Select id From #__menu Where `link`='index.php?option=com_contentbuilderng&viewcontainer=true' And parent_id = 1");
        $oldId = $db->loadResult();
        $parentId = $oldId;

        if (!$oldId) {
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

            $db->setQuery("Select max(mrgt.rgt)+1 From #__menu As mrgt");
            $rgt = $db->loadResult();

            $db->setQuery("Update `#__menu` Set rgt = " . $rgt . " Where `title` = 'Menu_Item_Root' And `alias` = 'root'");
            $db->execute();
        }

        $db->setQuery("Select id From #__menu Where link = 'index.php?option=com_contentbuilderng&task=list.display&id=" . (int) $contentbuilderngFormId . "'");
        $menuitem = $db->loadResult();

        if (!$update) {
            return;
        }
        if (!$result) {
            die("ContentBuilder NG main menu item not found!");
        }

        $db->setQuery("Select id From #__menu Where alias = " . $db->quote($name) . " And link Like 'index.php?option=com_contentbuilderng&task=list.display&id=%' And link <> 'index.php?option=com_contentbuilderng&task=list.display&id=" . (int) $contentbuilderngFormId . "'");
        $nameExists = $db->loadResult();

        if ($nameExists) {
            $name .= '_';
        }

        if (!$menuitem) {
            $db->setQuery(
                "insert into #__menu (
                    params,`path`,`title`, alias, menutype, type, parent_id,
                    link,
                    level, component_id, client_id, img,lft,rgt
                )
                values (
                    '',''," . $db->quote($name) . ", " . $db->quote($name) . ", 'main', 'component', '$parentId',
                    'index.php?option=com_contentbuilderng&task=list.display&id=" . (int) $contentbuilderngFormId . "',
                    1, " . (int) $result . ", 1, 'media/com_contentbuilderng/images/logo_icon_cb.png',
                    ( Select mlftrgt From (Select max(mlft.rgt)+1 As mlftrgt From #__menu As mlft) As tbone), ( Select mrgtrgt From (Select max(mrgt.rgt)+2 As mrgtrgt From #__menu As mrgt) As filet)
                )"
            );
            $db->execute();

            $db->setQuery("Select max(mrgt.rgt)+1 From #__menu As mrgt");
            $rgt = $db->loadResult();

            $db->setQuery("Update `#__menu` Set rgt = " . $rgt . " Where `title` = 'Menu_Item_Root' And `alias` = 'root'");
            $db->execute();
        } else {
            $db->setQuery(
                "Update #__menu Set `title` = " . $db->quote($name) . ", alias = " . $db->quote($name) . ", `parent_id` = '$parentId' Where id = $menuitem"
            );
            $db->execute();
        }
    }
}
