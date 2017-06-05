<?php
/**
 * Provide config options for the extension.
 *
 * @return array
 *   Array of:
 *   - whether to remove existing states/provinces,
 *   - ISO abbreviation of the country,
 *   - list of states/provinces with abbreviation,
 *   - list of states/provinces to rename,
 */
function malawidistricts_stateConfig() {
  $config = array(
    // CAUTION: only use `overwrite` on fresh databases.
    'overwrite' => TRUE,
    'countryIso' => 'MW',
    'states' => array(
      // 'state name' => 'abbreviation',
      'Balaka' => '1',
      'Blantyre' => '2',
      'Chikwawa' => '3',
      'Chiradzulu' => '4',
      'Chitipa' => '5',
      'Dedza' => '6',
      'Dowa' => '7',
      'Karonga' => '8',
      'Kasungu' => '9',
      'Likoma Islands' => '10',
      'Lilongwe' => '11',
      'Machinga' => '12',
      'Mangochi' => '13',
      'Mapuyu' => '14',
      'Mchinji' => '15',
      'Msinja' => '16',
      'Mulanje' => '17',
      'Mwanza' => '18',
      'Mzimba' => '19',
      'Mzuzu' => '20',
      'Neno' => '21',
      'Nkhatabay' => '22',
      'Nkhotakota' => '23',
      'Nsanje' => '24',
      'Ntcheu' => '25',
      'Ntchisi' => '26',
      'Phalombe' => '27',
      'Rumphi' => '28',
      'Salima' => '29',
      'Thyolo' => '30',
      'Zomba' => '31',
    ),
    'rewrites' => array(
      // List states to rewrite in the format:
      // 'Default State Name' => 'Corrected State Name',
    ),
  );
  return $config;
}
/**
 * Check and load states/provinces.
 *
 * @return bool
 *   Success true/false.
 */
function malawidistricts_loadProvinces() {
  $stateConfig = malawidistricts_stateConfig();
  if (empty($stateConfig['states']) || empty($stateConfig['countryIso'])) {
    return FALSE;
  }
  static $dao = NULL;
  if (!$dao) {
    $dao = new CRM_Core_DAO();
  }
  $statesToAdd = $stateConfig['states'];
  try {
    $countryId = civicrm_api3('Country', 'getvalue', array(
      'return' => 'id',
      'iso_code' => $stateConfig['countryIso'],
    ));
  }
  catch (CiviCRM_API3_Exception $e) {
    $error = $e->getMessage();
    CRM_Core_Error::debug_log_message(ts('API Error: %1', array(
      'domain' => 'org.ndi.malawidistricts',
      1 => $error,
    )));
    return FALSE;
  }
  // Rewrite states.
  if (!empty($stateConfig['rewrites'])) {
    foreach ($stateConfig['rewrites'] as $old => $new) {
      $sql = 'UPDATE civicrm_state_province SET name = %1 WHERE name = %2 and country_id = %3';
      $stateParams = array(
        1 => array(
          $new,
          'String',
        ),
        2 => array(
          $old,
          'String',
        ),
        3 => array(
          $countryId,
          'Integer',
        ),
      );
      CRM_Core_DAO::executeQuery($sql, $stateParams);
    }
  }
  // Find states that are already there.
  $stateIdsToKeep = array();
  foreach ($statesToAdd as $state => $abbr) {
    $sql = 'SELECT id FROM civicrm_state_province WHERE name = %1 AND country_id = %2 LIMIT 1';
    $stateParams = array(
      1 => array(
        $state,
        'String',
      ),
      2 => array(
        $countryId,
        'Integer',
      ),
    );
    $foundState = CRM_Core_DAO::singleValueQuery($sql, $stateParams);
    if ($foundState) {
      unset($statesToAdd[$state]);
      $stateIdsToKeep[] = $foundState;
      continue;
    }
  }
  // Wipe out states to remove.
  if (!empty($stateConfig['overwrite'])) {
    $sql = 'SELECT id FROM civicrm_state_province WHERE country_id = %1';
    $params = array(
      1 => array(
        $countryId,
        'Integer',
      ),
    );
    $dbStates = CRM_Core_DAO::executeQuery($sql, $params);
    $deleteIds = array();
    while ($dbStates->fetch()) {
      if (!in_array($dbStates->id, $stateIdsToKeep)) {
        $deleteIds[] = $dbStates->id;
      }
    }
    // Go delete the remaining old ones.
    foreach ($deleteIds as $id) {
      $sql = "DELETE FROM civicrm_state_province WHERE id = %1";
      $params = array(
        1 => array(
          $id,
          'Integer',
        ),
      );
      CRM_Core_DAO::executeQuery($sql, $params);
    }
  }
  // Add new states.
  $insert = array();
  foreach ($statesToAdd as $state => $abbr) {
    $stateE = $dao->escape($state);
    $abbrE = $dao->escape($abbr);
    $insert[] = "('$stateE', '$abbrE', $countryId)";
  }
  // Put it into queries of 50 states each.
  for ($i = 0; $i < count($insert); $i = $i + 50) {
    $inserts = array_slice($insert, $i, 50);
    $query = "INSERT INTO civicrm_state_province (name, abbreviation, country_id) VALUES ";
    $query .= implode(', ', $inserts);
    CRM_Core_DAO::executeQuery($query);
  }
  return TRUE;
}
/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function malawidistricts_civicrm_install() {
  malawidistricts_loadProvinces();
}
/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function malawidistricts_civicrm_enable() {
  malawidistricts_loadProvinces();
}
/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function malawidistricts_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  malawidistricts_loadProvinces();
}
