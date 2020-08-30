<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all menus
 *
 * @copyright 2004-2019 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

// Rechte pruefen
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$headline = $gL10n->get('SYS_MENU');

// create html page object
$page = new HtmlPage($headline);

$page->addJavascript('
    function moveMenu(direction, menID) {
        var actRow = document.getElementById("row_men_" + menID);
        var childs = actRow.parentNode.childNodes;
        var prevNode    = null;
        var nextNode    = null;
        var actRowCount = 0;
        var actSequence = 0;
        var secondSequence = 0;

        // erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
        for (i = 0; i < childs.length; i++) {
            if (childs[i].tagName === "TR") {
                actRowCount++;
                if (actSequence > 0 && nextNode === null) {
                    nextNode = childs[i];
                }

                if (childs[i].id === "row_men_" + menID) {
                    actSequence = actRowCount;
                }

                if (actSequence === 0) {
                    prevNode = childs[i];
                }
            }
        }

        // entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
        if (direction === "UP") {
            if (prevNode !== null) {
                actRow.parentNode.insertBefore(actRow, prevNode);
                secondSequence = actSequence - 1;
            }
        } else {
            if (nextNode !== null) {
                actRow.parentNode.insertBefore(nextNode, actRow);
                secondSequence = actSequence + 1;
            }
        }

        if (secondSequence > 0) {
            // Nun erst mal die neue Position von der gewaehlten Kategorie aktualisieren
            $.get("' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_function.php', array('mode' => 3)) . '&men_id=" + menID + "&sequence=" + direction);
        }
    }');

$gNavigation->addStartUrl(CURRENT_URL, $headline);

// define link to create new menu
$page->addPageFunctionsMenuItem('menu_item_menu_new', $gL10n->get('SYS_CREATE_ENTRY'), 
    ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_new.php', 'fa-plus-circle');

// Create table object
$menuOverview = new HtmlTable('tbl_menues', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TITLE'),
    '&nbsp;',
    $gL10n->get('ORG_URL'),
    '<i class="fas fa-star" data-toggle="tooltip" title="' . $gL10n->get('CAT_DEFAULT_VAR', array($gL10n->get('MEN_MENU_ITEM'))) . '"></i>',
    '&nbsp;'
);
$menuOverview->setColumnAlignByArray(array('left', 'left', 'left', 'center', 'right'));
$menuOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT men_id, men_name
          FROM '.TBL_MENU.'
         WHERE men_men_id_parent IS NULL
      ORDER BY men_order';
$mainMenStatement = $gDb->queryPrepared($sql);

while ($mainMen = $mainMenStatement->fetch())
{
    $sql = 'SELECT men_id, men_men_id_parent, men_name, men_description, men_standard, men_url
              FROM '.TBL_MENU.'
             WHERE men_men_id_parent = ? -- $mainMen[\'men_id\']
          ORDER BY men_men_id_parent DESC, men_order';
    $menuStatement = $gDb->queryPrepared($sql, array($mainMen['men_id']));

    $menuGroup = 0;

    // Get data
    while($menuRow = $menuStatement->fetch())
    {
        $menIdParent = (int) $menuRow['men_men_id_parent'];

        if($menuGroup !== $menIdParent)
        {
            $blockId = 'admMenu_'.$menIdParent;

            $menuOverview->addTableBody();
            $menuOverview->addRow('', array('class' => 'admidio-group-heading'));
            $menuOverview->addColumn('<span id="caret_'.$blockId.'" class="caret"></span>'.$gL10n->get($mainMen['men_name']),
                              array('id' => 'group_'.$blockId, 'colspan' => '8'));
            $menuOverview->addTableBody('id', $blockId);

            $menuGroup = $menIdParent;
        }

        $menuName = Language::translateIfTranslationStrId($menuRow['men_name']);
        $menuNameDesc = Language::translateIfTranslationStrId($menuRow['men_description']);

        // add root path to link unless the full URL is given
        if (preg_match('/^http(s?):\/\//', $menuRow['men_url']) === 0)
        {
            $menuLink = ADMIDIO_URL . $menuRow['men_url'];
        }
        else
        {
            $menuLink = $menuRow['men_url'];
        }

        $htmlMoveRow = '<a class="admidio-icon-link" href="javascript:moveMenu(\'UP\', '.$menuRow['men_id'].')">'.
                            '<i class="fas fa-chevron-circle-up" data-toggle="tooltip" title="' . $gL10n->get('CAT_MOVE_UP', array($headline)) . '"></i></a>
                        <a class="admidio-icon-link" href="javascript:moveMenu(\'DOWN\', '.$menuRow['men_id'].')">'.
                            '<i class="fas fa-chevron-circle-down" data-toggle="tooltip" title="' . $gL10n->get('CAT_MOVE_DOWN', array($headline)) . '"></i></a>';

        $htmlStandardMenu = '&nbsp;';
        if($menuRow['men_standard'])
        {
            $htmlStandardMenu = '<i class="fas fa-star" data-toggle="tooltip" title="' . $gL10n->get('CAT_DEFAULT_VAR', array($gL10n->get('MEN_MENU_ITEM'))) . '"></i>';
        }

        $menuAdministration = '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_new.php', array('men_id' => $menuRow['men_id'])). '">'.
                                '<i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i>';

        // don't allow delete for standard menus
        if(!$menuRow['men_standard'])
        {
            $menuAdministration .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);" 
                                        data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'men', 'element_id' => 'row_men_'.
                                        $menuRow['men_id'], 'name' => $menuName, 'database_id' => $menuRow['men_id'])).'">'.
                                        '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
        }

        // create array with all column values
        $columnValues = array(
            '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/menu/menu_new.php', array('men_id' => $menuRow['men_id'])). '" title="'.$menuNameDesc.'">'.$menuName.'</a>',
            $htmlMoveRow,
            '<a href="'.$menuLink. '" title="'.$menuNameDesc.'">'. $menuRow['men_url']. '</a>',
            $htmlStandardMenu,
            $menuAdministration
        );
        $menuOverview->addRowByArray($columnValues, 'row_men_'. $menuRow['men_id']);
    }
}

$page->addHtml($menuOverview->show());
$page->show();
