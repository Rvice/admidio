<?php
/**
 ***********************************************************************************************
 * Various common functions for the admidio module CategoryReport
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

if(!defined('ORG_ID'))
{
	define('ORG_ID', (int) $gCurrentOrganization->getValue('org_id'));
}

/**
 * Funktion prueft, ob ein User Angehoeriger einer bestimmten Kategorie ist
 *
 * @param   int  $cat_id    ID der zu pruefenden Kategorie
 * @param   int  $user_id   ID des Users, fuer den die Mitgliedschaft geprueft werden soll
 * @return  bool
 */
function isMemberOfCategorie($cat_id, $user_id = 0)
{
    global $gCurrentUser, $gDb;

    if ($user_id == 0)
    {
        $user_id = $gCurrentUser->getValue('usr_id');
    }
    elseif (is_numeric($user_id) == false)
    {
        return -1;
    }

    $sql    = 'SELECT mem_id
                 FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE mem_usr_id = ? -- $user_id
                  AND mem_begin <= ? -- DATE_NOW
                  AND mem_end    > ? -- DATE_NOW
                  AND mem_rol_id = rol_id
                  AND cat_id   = ? -- $cat_id
                  AND rol_valid  = 1
                  AND rol_cat_id = cat_id
                  AND (  cat_org_id = ? -- ORG_ID
                   OR cat_org_id IS NULL ) ';

    $queryParams = array(
        $user_id,
        DATE_NOW,
        DATE_NOW,
        $cat_id,
        ORG_ID
    );
    $statement = $gDb->queryPrepared($sql, $queryParams);
    $user_found = $statement->rowCount();

    if ($user_found == 1)
    {
        return 1;
    }
    else
    {
        return 0;
    }
}

/**
 * Funktion prüft, ob es eine Konfiguration mit dem übergebenen Namen bereits gibt
 * wenn ja: wird "- Kopie" angehängt und rekursiv überprüft
 * @param   string  $name
 * @return  string
 */
function createColDescConfig($name)
{
    global $config, $gL10n;

    while (in_array($name, $config['col_desc']))
    {
        $name .= ' - '.$gL10n->get('SYS_CARBON_COPY');
    }

    return $name;
}

/**
 * Funktion initialisiert das Konfigurationsarray
 * @param   none
 * @return  Array $config  das Konfigurationsarray
 */
function initConfigArray()
{
    global $gL10n, $gProfileFields;

    $config = array('col_desc' 		=> array($gL10n->get('SYS_PATTERN')),
                    'col_fields' 	=> array('p'.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').','.
                                             'p'.$gProfileFields->getProperty('LAST_NAME', 'usf_id').','.
                                             'p'.$gProfileFields->getProperty('STREET', 'usf_id').','.
                                             'p'.$gProfileFields->getProperty('CITY', 'usf_id')),
                    'selection_role'=> array(''),
                    'selection_cat'	=> array(''),
                    'number_col'	=> array(0)  );

    if (getRoleID($gL10n->get('SYS_ADMINISTRATOR')) > 0)
    {
        $config['col_fields'][0] .= ','.'r'.getRoleID($gL10n->get('SYS_ADMINISTRATOR'));
    }
    if (getRoleID($gL10n->get('INS_BOARD')) > 0)
    {
        $config['col_fields'][0] .= ','.'r'.getRoleID($gL10n->get('INS_BOARD'));
    }
    if (getRoleID($gL10n->get('SYS_MEMBER')) > 0)
    {
        $config['col_fields'][0] .= ','.'r'.getRoleID($gL10n->get('SYS_MEMBER'));
    }

    return $config;
}

/**
 * Funktion liest das Konfigurationsarray ein
 * @param   none
 * @return  Array $config  das Konfigurationsarray
 */
function getConfigArray()
{
    global  $gDb;

    $config = array();
    $i = 0;

    $tableName = TABLE_PREFIX . '_category_report';

    $sql = ' SELECT *
               FROM '.$tableName.'
              WHERE ( crt_org_id = ?
                 OR crt_org_id IS NULL ) ';
    $statement = $gDb->queryPrepared($sql, array(ORG_ID));

    while($row = $statement->fetch())
    {
        $config['col_desc'][$i]       = $row['crt_col_desc'];
        $config['col_fields'][$i]     = $row['crt_col_fields'];
        $config['selection_role'][$i] = $row['crt_selection_role'];
        $config['selection_cat'][$i]  = $row['crt_selection_cat'];
        $config['number_col'][$i]     = $row['crt_number_col'];
        ++$i;
    }
    return $config;
}

/**
 * Funktion speichert das Konfigurationsarray
 * @param   none
 */
function saveConfigArray()
{
    global  $config, $gDb;

    $tableName = TABLE_PREFIX . '_category_report';
    $numConfig = count($config['col_desc']);
    $crtDb = array();

    $sql = ' SELECT crt_id
               FROM '.$tableName.'
              WHERE ( crt_org_id = ?
                 OR crt_org_id IS NULL ) ';
    $statement = $gDb->queryPrepared($sql, array(ORG_ID));

    while($row = $statement->fetch())
    {
        $crtDb[] = $row['crt_id'];
    }

    $numCrtDb = count($crtDb);

    for($i = $numConfig; $i < $numCrtDb; ++$i)
    {
        $categoryReport = new TableAccess($gDb, TABLE_PREFIX . '_category_report', 'crt', $crtDb[$i]);
        $categoryReport->delete();
        unset($crtDb[$i]);
    }

    foreach ($config['col_desc'] as $i => $dummy)
    {
        $categoryReport = new TableAccess($gDb, TABLE_PREFIX . '_category_report', 'crt');
        if (isset($crtDb[$i]))
        {
            $categoryReport->readDataById($crtDb[$i]);
        }

        $categoryReport->setValue('crt_org_id', ORG_ID);
        $categoryReport->setValue('crt_col_desc', $config['col_desc'][$i]);
        $categoryReport->setValue('crt_col_fields', $config['col_fields'][$i]);
        $categoryReport->setValue('crt_selection_role', $config['selection_role'][$i]);
        $categoryReport->setValue('crt_selection_cat', $config['selection_cat'][$i]);
        $categoryReport->setValue('crt_number_col', $config['number_col'][$i]);
        $categoryReport->save();
    }
    return;
}

/**
 * Funktion liest die Rollen-ID einer Rolle aus
 * @param   string  $role_name Name der zu pruefenden Rolle
 * @return  int     rol_id  Rollen-ID der Rolle; 0, wenn nicht gefunden
 */
function getRoleID($role_name)
{
    global $gDb;

    $sql = 'SELECT rol_id
              FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
             WHERE rol_name   = ? -- $role_name
               AND rol_valid  = 1
               AND rol_cat_id = cat_id
               AND ( cat_org_id = ? -- ORG_ID
                OR cat_org_id IS NULL ) ';

    $statement = $gDb->queryPrepared($sql, array($role_name, ORG_ID));
    $row = $statement->fetchObject();

    return (isset($row->rol_id) ? $row->rol_id : 0);
}

