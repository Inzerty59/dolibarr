<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$langs->load("mymodule@mymodule");

function monday_normalize_kpi_label($label)
{
    $label = dol_string_unaccent((string) $label);
    $label = strtolower($label);
    $label = preg_replace('/[^a-z0-9]+/', '', $label);
    return $label;
}

function monday_get_kpi_columns($db, $workspaceId = 0)
{
    $targets = [
        'retourclient' => 'retour_client',
        'motifrefus' => 'motif_refus',
        'canalsourcing' => 'canal_sourcing',
        'client' => 'client',
        'dateenvoieclient' => 'date_envoie_client',
        'dateenvoiclient' => 'date_envoie_client',
        'datedenvoiclient' => 'date_envoie_client',
        'datedenvoieclient' => 'date_envoie_client',
        'dateretour' => 'date_retour',
        'actioncorrective' => 'action_corrective',
    ];

    $workspaceCondition = '';
    $workspaceId = (int) $workspaceId;
    if ($workspaceId > 0) {
        $workspaceCondition = ' WHERE c.fk_workspace = '.$workspaceId;
    }

    $columns = [];
    // On ignore les colonnes orphelines ou rattachees a un groupe d'un autre workspace.
    $res = $db->query("SELECT c.rowid, c.fk_group, c.label, c.type
                         FROM llx_myworkspace_column c
                         JOIN llx_myworkspace_group g ON g.rowid = c.fk_group
                          AND g.fk_workspace = c.fk_workspace
                      ".$workspaceCondition);
    while ($res && $o = $db->fetch_object($res)) {
        $normalized = monday_normalize_kpi_label($o->label);
        if (isset($targets[$normalized])) {
            $columns[] = [
                'id' => (int) $o->rowid,
                'group_id' => (int) $o->fk_group,
                'metric' => $targets[$normalized],
                'type' => (string) $o->type,
            ];
        }
    }

    return $columns;
}

function monday_sanitize_kpi_color($color)
{
    $color = trim((string) $color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#cccccc';
}

function monday_get_kpi_select_options($db, $columns)
{
    if (empty($columns)) {
        return [];
    }

    $columnIds = array_map(function ($column) {
        return (int) $column['id'];
    }, $columns);

    $options = [];
    $sql = "SELECT rowid, fk_column, label, color
              FROM llx_myworkspace_column_option
             WHERE fk_column IN (".implode(',', $columnIds).")
          ORDER BY position ASC, rowid ASC";
    $res = $db->query($sql);
    while ($res && $o = $db->fetch_object($res)) {
        $options[(int) $o->rowid] = [
            'id' => (int) $o->rowid,
            'column_id' => (int) $o->fk_column,
            'label' => (string) $o->label,
            'color' => monday_sanitize_kpi_color($o->color),
        ];
    }

    return $options;
}

function monday_empty_kpi_bucket()
{
    return ['count' => 0, 'color' => '#e5e7eb'];
}

function monday_parse_kpi_date($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (strpos($value, '|') !== false) {
        $parts = array_values(array_filter(array_map('trim', explode('|', $value)), function ($part) {
            return $part !== '';
        }));
        $value = isset($parts[0]) ? $parts[0] : '';
        if ($value === '') {
            return null;
        }
    }

    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date && $date->format($format) === $value) {
            $date->setTime(0, 0, 0);
            return $date;
        }
    }

    return null;
}

function monday_format_delay_bucket($days)
{
    $days = (int) $days;
    if ($days === 0) {
        return 'Même jour';
    }
    if ($days === 1) {
        return '1 jour';
    }
    if ($days === 7) {
        return '1 semaine';
    }
    if ($days > 7 && $days % 7 === 0 && $days < 60) {
        return ($days / 7).' semaines';
    }
    if ($days === 30) {
        return '1 mois';
    }
    if ($days > 30 && $days % 30 === 0) {
        return ($days / 30).' mois';
    }
    return $days.' jours';
}

function monday_format_average_delay($days)
{
    if ($days === null) {
        return 'Aucune donnée';
    }
    if ($days < 1) {
        return round($days * 24, 1).' h';
    }
    $rounded = round($days, 1);
    return $rounded.' '.($rounded > 1 ? 'jours' : 'jour');
}

function monday_is_kpi_date_in_range($date, $startDate, $endDate)
{
    // Filtre metier: une ligne filtree doit avoir la date presente et dans la periode.
    if (!$date) {
        return false;
    }
    if ($startDate && $date < $startDate) {
        return false;
    }
    if ($endDate && $date > $endDate) {
        return false;
    }
    return true;
}

function monday_delete_task_tree($db, $taskId)
{
    $taskId = (int) $taskId;
    $res = $db->query("SELECT rowid FROM llx_myworkspace_task WHERE parent_task_id = $taskId");
    while ($res && $subtask = $db->fetch_object($res)) {
        monday_delete_task_tree($db, (int) $subtask->rowid);
    }

    // La base existante n'a pas toutes les FK en cascade: on nettoie les enfants a la main.
    $db->query("DELETE cf
                  FROM llx_myworkspace_comment_file cf
                  JOIN llx_myworkspace_comment c ON c.rowid = cf.fk_comment
                 WHERE c.fk_task = $taskId");
    $db->query("DELETE FROM llx_myworkspace_task_file WHERE fk_task = $taskId");
    $db->query("DELETE FROM llx_myworkspace_comment WHERE fk_task = $taskId");
    $db->query("DELETE FROM llx_myworkspace_cell WHERE fk_task = $taskId");
    $db->query("DELETE FROM llx_myworkspace_task WHERE rowid = $taskId");
}

function monday_delete_column_data($db, $columnId)
{
    $columnId = (int) $columnId;
    $db->query("DELETE FROM llx_myworkspace_cell WHERE fk_column = $columnId");
    $db->query("DELETE FROM llx_myworkspace_column_option WHERE fk_column = $columnId");
    $db->query("DELETE FROM llx_myworkspace_column WHERE rowid = $columnId");
}

function monday_delete_group_data($db, $groupId)
{
    $groupId = (int) $groupId;

    // Un tableau Planity = groupe + lignes + colonnes + cellules + pieces/commentaires.
    $resTasks = $db->query("SELECT rowid FROM llx_myworkspace_task WHERE fk_group = $groupId");
    while ($resTasks && $task = $db->fetch_object($resTasks)) {
        monday_delete_task_tree($db, (int) $task->rowid);
    }

    $resColumns = $db->query("SELECT rowid FROM llx_myworkspace_column WHERE fk_group = $groupId");
    while ($resColumns && $column = $db->fetch_object($resColumns)) {
        monday_delete_column_data($db, (int) $column->rowid);
    }

    $db->query("DELETE FROM llx_myworkspace_group WHERE rowid = $groupId");
}

function monday_delete_workspace_data($db, $workspaceId)
{
    $workspaceId = (int) $workspaceId;

    // On supprime les tableaux avant l'espace pour ne pas fabriquer d'orphelins.
    $resGroups = $db->query("SELECT rowid FROM llx_myworkspace_group WHERE fk_workspace = $workspaceId");
    while ($resGroups && $group = $db->fetch_object($resGroups)) {
        monday_delete_group_data($db, (int) $group->rowid);
    }

    $db->query("DELETE FROM llx_myworkspace WHERE rowid = $workspaceId");
}

function monday_get_kpi_context($db, $workspaceId = 0)
{
    $kpiColumns = monday_get_kpi_columns($db, $workspaceId);
    $options = monday_get_kpi_select_options($db, $kpiColumns);
    $columnsByGroup = [];

    foreach ($kpiColumns as $column) {
        if (!isset($columnsByGroup[$column['group_id']])) {
            $columnsByGroup[$column['group_id']] = [];
        }
        $columnsByGroup[$column['group_id']][$column['metric']] = $column['id'];
    }

    $dataGroupIds = [];
    foreach ($columnsByGroup as $groupId => $groupColumns) {
        if (isset($groupColumns['retour_client']) || isset($groupColumns['motif_refus']) || isset($groupColumns['canal_sourcing']) || isset($groupColumns['date_envoie_client']) || isset($groupColumns['date_retour']) || isset($groupColumns['action_corrective'])) {
            $dataGroupIds[] = (int) $groupId;
        }
    }

    return [$kpiColumns, $options, $columnsByGroup, $dataGroupIds];
}

function monday_get_kpi_recruitment_workspace_id($db)
{
    $res = $db->query("SELECT rowid, label FROM llx_myworkspace ORDER BY position ASC, rowid ASC");
    while ($res && $workspace = $db->fetch_object($res)) {
        if (monday_normalize_kpi_label($workspace->label) === 'kpirecrutement') {
            return (int) $workspace->rowid;
        }
    }

    return 0;
}

function monday_get_kpi_export_groups($db)
{
    $groups = [];
    $workspaceId = monday_get_kpi_recruitment_workspace_id($db);

    if ($workspaceId > 0) {
        $res = $db->query("SELECT rowid, label
                             FROM llx_myworkspace_group
                            WHERE fk_workspace = ".$workspaceId."
                         ORDER BY position ASC, label ASC");
        while ($res && $group = $db->fetch_object($res)) {
            $groups[] = [
                'id' => (int) $group->rowid,
                'label' => (string) $group->label,
            ];
        }
    }

    return $groups;
}

function monday_get_kpi_cell_label($value, $options)
{
    $optionId = (int) $value;
    if ($optionId > 0 && isset($options[$optionId])) {
        return $options[$optionId]['label'];
    }
    return (string) $value;
}

function monday_csv_safe_value($value)
{
    $value = (string) $value;
    if ($value !== '' && preg_match('/^[=+\-@]/', $value)) {
        return "'".$value;
    }
    return $value;
}

function monday_csv_put_row($handle, $row)
{
    $safeRow = array_map('monday_csv_safe_value', $row);
    fputcsv($handle, $safeRow, ';');
}

function monday_get_column_options_for_export($db, $columnIds)
{
    if (empty($columnIds)) {
        return [];
    }

    $columnIds = array_map('intval', $columnIds);
    $options = [];
    $res = $db->query("SELECT rowid, fk_column, label
                         FROM llx_myworkspace_column_option
                        WHERE fk_column IN (".implode(',', $columnIds).")
                     ORDER BY position ASC, rowid ASC");
    while ($res && $option = $db->fetch_object($res)) {
        $columnId = (int) $option->fk_column;
        if (!isset($options[$columnId])) {
            $options[$columnId] = [];
        }
        $options[$columnId][(int) $option->rowid] = (string) $option->label;
    }

    return $options;
}

function monday_get_users_for_export($db)
{
    $users = [];
    $res = $db->query("SELECT rowid, firstname, lastname, login FROM llx_user");
    while ($res && $user = $db->fetch_object($res)) {
        $name = trim($user->firstname.' '.$user->lastname);
        $users[(int) $user->rowid] = $name !== '' ? $name : (string) $user->login;
    }
    return $users;
}

function monday_format_export_cell_value($value, $column, $optionsByColumn, $usersById)
{
    $value = (string) $value;
    if ($value === '') {
        return '';
    }

    $columnId = (int) $column['id'];
    switch ($column['type']) {
        case 'select':
            $optionId = (int) $value;
            return isset($optionsByColumn[$columnId][$optionId]) ? $optionsByColumn[$columnId][$optionId] : $value;

        case 'tags':
            $tagIds = json_decode($value, true);
            if (!is_array($tagIds)) {
                return $value;
            }
            $labels = [];
            foreach ($tagIds as $tagId) {
                $tagId = (int) $tagId;
                if (isset($optionsByColumn[$columnId][$tagId])) {
                    $labels[] = $optionsByColumn[$columnId][$tagId];
                }
            }
            return implode(', ', $labels);

        case 'user':
            $userId = (int) $value;
            return isset($usersById[$userId]) ? $usersById[$userId] : $value;

        case 'deadline':
            return str_replace('|', ' - ', $value);

        default:
            return $value;
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST'
    && isset($_POST['toggle_group_id'], $_POST['collapsed'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $gid       = (int)$_POST['toggle_group_id'];
    $collapsed = (int)$_POST['collapsed'];
    $db->query("
        UPDATE llx_myworkspace_group
           SET collapsed = $collapsed
         WHERE rowid = $gid
    ");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_task_completion'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $taskId = (int)$_POST['toggle_task_completion'];
    $isCompleted = (int)$_POST['is_completed'];
    
    $db->query("UPDATE llx_myworkspace_task SET is_completed = $isCompleted WHERE rowid = $taskId");
    echo 'OK';
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['tasks_group_id'])) {
    $gid = (int)$_GET['tasks_group_id'];
    $res = $db->query("SELECT rowid, label, parent_task_id, level_depth, is_completed FROM llx_myworkspace_task WHERE fk_group = $gid ORDER BY position ASC");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[] = [
            'id'=>$o->rowid,
            'label'=>$o->label,
            'parent_task_id'=>$o->parent_task_id,
            'level_depth'=>$o->level_depth,
            'is_completed'=>(int)$o->is_completed
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

// Nouvel endpoint : retourne les tâches + cellules en une seule requête
if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['tasks_group_id_with_cells'])) {
    $gid = (int)$_GET['tasks_group_id_with_cells'];
    
    // Récupérer les tâches
    $res = $db->query("SELECT rowid, label, parent_task_id, level_depth, is_completed FROM llx_myworkspace_task WHERE fk_group = $gid ORDER BY position ASC");
    $out = [];
    $taskIds = [];
    while ($o = $db->fetch_object($res)) {
        $taskIds[] = $o->rowid;
        $out[] = [
            'id'=>$o->rowid,
            'label'=>$o->label,
            'parent_task_id'=>$o->parent_task_id,
            'level_depth'=>$o->level_depth,
            'is_completed'=>(int)$o->is_completed,
            'cells' => []
        ];
    }
    
    // Récupérer toutes les cellules pour toutes les tâches du groupe en une seule requête
    if (!empty($taskIds)) {
        $taskIdsList = implode(',', $taskIds);
        $res = $db->query("SELECT fk_task, fk_column, value FROM llx_myworkspace_cell WHERE fk_task IN ($taskIdsList)");
        $cellsByTask = [];
        while ($o = $db->fetch_object($res)) {
            if (!isset($cellsByTask[$o->fk_task])) {
                $cellsByTask[$o->fk_task] = [];
            }
            $cellsByTask[$o->fk_task][$o->fk_column] = $o->value;
        }
        
        // Ajouter les cellules aux tâches
        foreach ($out as &$task) {
            if (isset($cellsByTask[$task['id']])) {
                $task['cells'] = $cellsByTask[$task['id']];
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['kpi_export_groups'])) {
    if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['newtoken']) {
        accessforbidden('CSRF token invalid');
    }

    $groups = monday_get_kpi_export_groups($db);

    header('Content-Type: application/json');
    echo json_encode($groups);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['kpi_export_csv'])) {
    if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['newtoken']) {
        accessforbidden('CSRF token invalid');
    }

    $groups = monday_get_kpi_export_groups($db);
    $selected = trim((string) $_GET['kpi_export_csv']);
    $selectedGroupId = $selected === 'all' ? 0 : (int) $selected;

    $groupIds = array_map(function ($group) {
        return (int) $group['id'];
    }, $groups);

    if ($selectedGroupId > 0 && !in_array($selectedGroupId, $groupIds, true)) {
        accessforbidden('Invalid KPI export group');
    }

    $exportGroupIds = [];
    if ($selectedGroupId > 0) {
        $exportGroupIds[] = $selectedGroupId;
    } else if (!empty($groupIds)) {
        $exportGroupIds = $groupIds;
    }

    $groups = [];
    if (!empty($exportGroupIds)) {
        $resGroups = $db->query("SELECT rowid, label, task_column_label
                                   FROM llx_myworkspace_group
                                  WHERE rowid IN (".implode(',', array_map('intval', $exportGroupIds)).")
                               ORDER BY position ASC, rowid ASC");
        while ($resGroups && $group = $db->fetch_object($resGroups)) {
            $groups[(int) $group->rowid] = [
                'id' => (int) $group->rowid,
                'label' => (string) $group->label,
                'task_column_label' => $group->task_column_label ?: 'Tâche',
                'columns' => [],
                'tasks' => [],
            ];
        }
    }

    $allColumnIds = [];
    if (!empty($groups)) {
        $resColumns = $db->query("SELECT rowid, fk_group, label, type
                                    FROM llx_myworkspace_column
                                   WHERE fk_group IN (".implode(',', array_keys($groups)).")
                                ORDER BY fk_group ASC, position ASC, rowid ASC");
        while ($resColumns && $column = $db->fetch_object($resColumns)) {
            $groupId = (int) $column->fk_group;
            if (!isset($groups[$groupId])) {
                continue;
            }
            $columnData = [
                'id' => (int) $column->rowid,
                'label' => (string) $column->label,
                'type' => (string) $column->type,
            ];
            $groups[$groupId]['columns'][] = $columnData;
            $allColumnIds[] = $columnData['id'];
        }
    }

    $optionsByColumn = monday_get_column_options_for_export($db, $allColumnIds);
    $usersById = monday_get_users_for_export($db);

    $allTaskIds = [];
    foreach ($groups as $groupId => &$group) {
        $resTasks = $db->query("SELECT t.rowid, t.label
                                  FROM llx_myworkspace_task t
                                 WHERE t.fk_group = ".(int) $groupId."
                              ORDER BY t.position ASC, t.rowid ASC");
        while ($resTasks && $task = $db->fetch_object($resTasks)) {
            $taskId = (int) $task->rowid;
            $allTaskIds[] = $taskId;
            $group['tasks'][$taskId] = [
                'id' => $taskId,
                'label' => (string) $task->label,
                'cells' => [],
            ];
        }
    }
    unset($group);

    if (!empty($allTaskIds)) {
        $resCells = $db->query("SELECT fk_task, fk_column, value
                                  FROM llx_myworkspace_cell
                                 WHERE fk_task IN (".implode(',', array_map('intval', $allTaskIds)).")");
        while ($resCells && $cell = $db->fetch_object($resCells)) {
            $taskId = (int) $cell->fk_task;
            foreach ($groups as &$group) {
                if (isset($group['tasks'][$taskId])) {
                    $group['tasks'][$taskId]['cells'][(int) $cell->fk_column] = (string) $cell->value;
                    break;
                }
            }
            unset($group);
        }
    }

    $groupsById = [];
    foreach ($groups as $group) {
        $groupsById[$group['id']] = $group;
    }
    $filenamePart = $selectedGroupId > 0 && isset($groupsById[$selectedGroupId]) ? dol_string_nospecial($groupsById[$selectedGroupId]['label'], '-') : 'tous';
    $filename = 'kpi-recrutement-'.$filenamePart.'-'.date('Ymd-His').'.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    $out = fopen('php://output', 'w');
    fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));

    $isFirstGroup = true;
    foreach ($groups as $group) {
        if (!$isFirstGroup) {
            monday_csv_put_row($out, []);
        }
        $isFirstGroup = false;

        if ($selectedGroupId === 0) {
            monday_csv_put_row($out, [$group['label']]);
        }

        $header = [$group['task_column_label']];
        foreach ($group['columns'] as $column) {
            $header[] = $column['label'];
        }
        monday_csv_put_row($out, $header);

        foreach ($group['tasks'] as $task) {
            $row = [$task['label']];
            foreach ($group['columns'] as $column) {
                $value = isset($task['cells'][$column['id']]) ? $task['cells'][$column['id']] : '';
                $row[] = monday_format_export_cell_value($value, $column, $optionsByColumn, $usersById);
            }
            monday_csv_put_row($out, $row);
        }
    }

    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['kpi_recruitment'])) {
    if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['newtoken']) {
        accessforbidden('CSRF token invalid');
    }

    $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
    $year = isset($_GET['year']) ? (int) $_GET['year'] : 0;
    $clientFilter = isset($_GET['client']) ? trim($_GET['client']) : '';

    if ($year > 0) {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);
    }

    $filterStartDate = null;
    $filterEndDate = null;
    $hasDateFilter = false;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $filterStartDate = monday_parse_kpi_date($startDate);
        $hasDateFilter = $filterStartDate !== null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $filterEndDate = monday_parse_kpi_date($endDate);
        $hasDateFilter = $hasDateFilter || $filterEndDate !== null;
    }

    $kpiWorkspaceId = monday_get_kpi_recruitment_workspace_id($db);
    if ($kpiWorkspaceId > 0) {
        list($kpiColumns, $options, $columnsByGroup, $dataGroupIds) = monday_get_kpi_context($db, $kpiWorkspaceId);
    } else {
        $kpiColumns = [];
        $options = [];
        $columnsByGroup = [];
        $dataGroupIds = [];
    }

    $metrics = [
        'retour_client' => [
            'title' => 'Retour client',
            'empty_label' => 'Aucune valeur choisie',
            'items' => [],
        ],
        'motif_refus' => [
            'title' => 'Motif refus',
            'empty_label' => 'Aucune valeur choisie',
            'items' => [],
        ],
        'canal_sourcing' => [
            'title' => 'Canaux de sourcing',
            'empty_label' => 'Aucune valeur choisie',
            'items' => [],
        ],
    ];

    $clientChoices = [];
    foreach ($kpiColumns as $column) {
        if ($column['metric'] !== 'client') {
            continue;
        }
        if (!in_array($column['group_id'], $dataGroupIds, true)) {
            continue;
        }
        foreach ($options as $option) {
            if ($option['column_id'] === $column['id']) {
                $clientChoices[$option['label']] = true;
            }
        }
    }
    ksort($clientChoices, SORT_NATURAL | SORT_FLAG_CASE);

    $taskConditions = [];
    if (!empty($dataGroupIds)) {
        $taskConditions[] = "t.fk_group IN (".implode(',', $dataGroupIds).")";
    } else {
        $taskConditions[] = "1 = 0";
    }

    $where = !empty($taskConditions) ? ' WHERE '.implode(' AND ', $taskConditions) : '';
    $resTasks = $db->query("SELECT t.rowid, t.fk_group
                              FROM llx_myworkspace_task t
                              JOIN llx_myworkspace_group g ON g.rowid = t.fk_group".$where);

    $taskIds = [];
    $tasks = [];
    while ($resTasks && $task = $db->fetch_object($resTasks)) {
        $taskId = (int) $task->rowid;
        $taskIds[] = $taskId;
        $tasks[$taskId] = [
            'id' => $taskId,
            'group_id' => (int) $task->fk_group,
            'cells' => [],
        ];
    }

    $kpiColumnIds = array_map(function ($column) {
        return (int) $column['id'];
    }, $kpiColumns);

    if (!empty($taskIds) && !empty($kpiColumnIds)) {
        $resCells = $db->query("SELECT cell.fk_task, cell.fk_column, cell.value
                                  FROM llx_myworkspace_cell cell
                                  JOIN llx_myworkspace_task t ON t.rowid = cell.fk_task
                                  JOIN llx_myworkspace_column c ON c.rowid = cell.fk_column
                                   AND c.fk_group = t.fk_group
                                 WHERE cell.fk_task IN (".implode(',', $taskIds).")
                                   AND cell.fk_column IN (".implode(',', $kpiColumnIds).")");
        while ($resCells && $cell = $db->fetch_object($resCells)) {
            $taskId = (int) $cell->fk_task;
            if (isset($tasks[$taskId])) {
                $tasks[$taskId]['cells'][(int) $cell->fk_column] = (string) $cell->value;
            }
        }
    }

    $totalRows = 0;
    $validDelayRows = 0;
    $delayTotalDays = 0;
    $delayBuckets = [];
    $actionCorrectiveBuckets = [];
    $actionCorrectiveRows = 0;

    foreach ($tasks as $task) {
        $groupColumns = isset($columnsByGroup[$task['group_id']]) ? $columnsByGroup[$task['group_id']] : [];
        $sentColumnId = isset($groupColumns['date_envoie_client']) ? $groupColumns['date_envoie_client'] : 0;
        $returnColumnId = isset($groupColumns['date_retour']) ? $groupColumns['date_retour'] : 0;
        $sentDate = $sentColumnId && isset($task['cells'][$sentColumnId]) ? monday_parse_kpi_date($task['cells'][$sentColumnId]) : null;
        $returnDate = $returnColumnId && isset($task['cells'][$returnColumnId]) ? monday_parse_kpi_date($task['cells'][$returnColumnId]) : null;

        if ($hasDateFilter && (!monday_is_kpi_date_in_range($sentDate, $filterStartDate, $filterEndDate) || !monday_is_kpi_date_in_range($returnDate, $filterStartDate, $filterEndDate))) {
            continue;
        }

        $clientColumnId = isset($groupColumns['client']) ? $groupColumns['client'] : 0;
        $clientOptionId = $clientColumnId && isset($task['cells'][$clientColumnId]) ? (int) $task['cells'][$clientColumnId] : 0;
        $clientLabel = isset($options[$clientOptionId]) ? $options[$clientOptionId]['label'] : '';

        if ($clientFilter !== '' && strcasecmp($clientLabel, $clientFilter) !== 0) {
            continue;
        }

        $totalRows++;
        foreach ($metrics as $metricKey => $metric) {
            $columnId = isset($groupColumns[$metricKey]) ? $groupColumns[$metricKey] : 0;
            $optionId = $columnId && isset($task['cells'][$columnId]) ? (int) $task['cells'][$columnId] : 0;

            if ($optionId > 0 && isset($options[$optionId])) {
                $label = $options[$optionId]['label'];
                if (!isset($metrics[$metricKey]['items'][$label])) {
                    $metrics[$metricKey]['items'][$label] = [
                        'count' => 0,
                        'color' => $options[$optionId]['color'],
                    ];
                }
                $metrics[$metricKey]['items'][$label]['count']++;
            } else {
                $emptyLabel = $metrics[$metricKey]['empty_label'];
                if (!isset($metrics[$metricKey]['items'][$emptyLabel])) {
                    $metrics[$metricKey]['items'][$emptyLabel] = monday_empty_kpi_bucket();
                }
                $metrics[$metricKey]['items'][$emptyLabel]['count']++;
            }
        }

        if ($sentDate && $returnDate) {
            $delayDays = (int) $sentDate->diff($returnDate)->format('%r%a');
            if ($delayDays >= 0) {
                $validDelayRows++;
                $delayTotalDays += $delayDays;
                $bucket = monday_format_delay_bucket($delayDays);
                if (!isset($delayBuckets[$bucket])) {
                    $delayBuckets[$bucket] = ['label' => $bucket, 'count' => 0, 'days' => $delayDays];
                }
                $delayBuckets[$bucket]['count']++;
            }
        }

        $actionColumnId = isset($groupColumns['action_corrective']) ? $groupColumns['action_corrective'] : 0;
        $actionValue = $actionColumnId && isset($task['cells'][$actionColumnId]) ? trim(preg_replace('/\s+/', ' ', (string) $task['cells'][$actionColumnId])) : '';
        if ($actionColumnId && $actionValue !== '') {
            $actionValue = monday_get_kpi_cell_label($actionValue, $options);
            $actionValue = trim(preg_replace('/\s+/', ' ', $actionValue));
        }
        if ($actionValue === '') {
            $actionValue = 'Aucune action corrective';
        } else {
            $actionCorrectiveRows++;
        }
        if ($actionValue !== '') {
            $actionKey = monday_normalize_kpi_label($actionValue);
            if (!isset($actionCorrectiveBuckets[$actionKey])) {
                $actionCorrectiveBuckets[$actionKey] = [
                    'label' => $actionValue,
                    'count' => 0,
                    'color' => $actionValue === 'Aucune action corrective' ? '#e5e7eb' : '#6b5fad',
                ];
            }
            $actionCorrectiveBuckets[$actionKey]['count']++;
        }
    }

    foreach ($metrics as $metricKey => $metric) {
        $series = [];
        foreach ($metric['items'] as $label => $item) {
            $percentage = $totalRows > 0 ? round(($item['count'] / $totalRows) * 100, 1) : 0;
            $series[] = [
                'label' => $label,
                'count' => $item['count'],
                'percentage' => $percentage,
                'color' => $item['color'],
            ];
        }
        usort($series, function ($a, $b) {
            if ($a['count'] === $b['count']) {
                return strcasecmp($a['label'], $b['label']);
            }
            return $b['count'] - $a['count'];
        });
        $metrics[$metricKey]['series'] = $series;
        unset($metrics[$metricKey]['items']);
    }

    $delayAverage = $validDelayRows > 0 ? $delayTotalDays / $validDelayRows : null;
    $delaySeries = array_values($delayBuckets);
    usort($delaySeries, function ($a, $b) {
        if ($a['count'] === $b['count']) {
            return $a['days'] - $b['days'];
        }
        return $b['count'] - $a['count'];
    });
    $delaySeries = array_slice($delaySeries, 0, 4);
    foreach ($delaySeries as &$delayItem) {
        $delayItem['percentage'] = $validDelayRows > 0 ? round(($delayItem['count'] / $validDelayRows) * 100, 1) : 0;
    }
    unset($delayItem);

    $actionSeries = array_values($actionCorrectiveBuckets);
    usort($actionSeries, function ($a, $b) {
        if ($a['count'] === $b['count']) {
            return strcasecmp($a['label'], $b['label']);
        }
        return $b['count'] - $a['count'];
    });
    foreach ($actionSeries as &$actionItem) {
        $actionItem['percentage'] = $totalRows > 0 ? round(($actionItem['count'] / $totalRows) * 100, 1) : 0;
    }
    unset($actionItem);

    header('Content-Type: application/json');
    echo json_encode([
        'total' => $totalRows,
        'filters' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'year' => $year,
            'client' => $clientFilter,
        ],
        'clients' => array_keys($clientChoices),
        'metrics' => array_values($metrics),
        'response_delay' => [
            'title' => 'Délai moyen de réponse client',
            'average_days' => $delayAverage,
            'average_label' => monday_format_average_delay($delayAverage),
            'valid_rows' => $validDelayRows,
            'series' => $delaySeries,
        ],
        'action_corrective' => [
            'title' => 'Actions correctives',
            'filled' => $actionCorrectiveRows,
            'total' => $totalRows,
            'series' => $actionSeries,
        ],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_task_group_id'], $_POST['task_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $gid   = (int)$_POST['add_task_group_id'];
    $label = $db->escape($_POST['task_label']);
    $datec = date('Y-m-d H:i:s');
    
    $parent_task_id = isset($_POST['parent_task_id']) ? (int)$_POST['parent_task_id'] : null;
    $level_depth = 0;
    
    if ($parent_task_id) {
        $r = $db->query("SELECT level_depth FROM llx_myworkspace_task WHERE rowid=$parent_task_id");
        if ($o = $db->fetch_object($r)) {
            $level_depth = $o->level_depth + 1;
        }
        $r = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_task WHERE fk_group=$gid AND parent_task_id=$parent_task_id");
    } else {
        $r = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_task WHERE fk_group=$gid AND parent_task_id IS NULL");
    }
    
    $p = ($r && $o=$db->fetch_object($r)) ? $o->m+1 : 0;
    
    if ($parent_task_id) {
        $db->query("INSERT INTO llx_myworkspace_task (fk_group,label,position,datec,parent_task_id,level_depth) VALUES ($gid,'$label',$p,'$datec',$parent_task_id,$level_depth)");
    } else {
        $db->query("INSERT INTO llx_myworkspace_task (fk_group,label,position,datec,level_depth) VALUES ($gid,'$label',$p,'$datec',$level_depth)");
    }

    $newTaskId = (int) $db->last_insert_id('llx_myworkspace_task');

    if ($newTaskId > 0 && !empty($_POST['split_column_id']) && !empty($_POST['split_option_id'])) {
        $splitColumnId = (int) $_POST['split_column_id'];
        $splitOptionId = (int) $_POST['split_option_id'];


        $db->query("INSERT INTO llx_myworkspace_cell (fk_task, fk_column, value)
                    VALUES ($newTaskId, $splitColumnId, '$splitOptionId')
                    ON DUPLICATE KEY UPDATE value = '$splitOptionId'");
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rename_task_id'], $_POST['rename_task_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid   = (int)$_POST['rename_task_id'];
    $label = $db->escape($_POST['rename_task_label']);
    $db->query("UPDATE llx_myworkspace_task SET label='$label' WHERE rowid=$tid");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_task_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid = (int)$_POST['delete_task_id'];
    $db->begin();
    monday_delete_task_tree($db, $tid);
    $db->commit();
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_tasks'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_tasks'], true);
    foreach ($order as $i=>$tid) {
        $db->query("UPDATE llx_myworkspace_task SET position=$i WHERE rowid=".(int)$tid);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_workspaces'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_workspaces'], true);
    foreach ($order as $i=>$id) {
        $db->query("UPDATE llx_myworkspace SET position=$i WHERE rowid=".(int)$id);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_groups'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_groups'], true);
    foreach ($order as $i=>$id) {
        $db->query("UPDATE llx_myworkspace_group SET position=$i WHERE rowid=".(int)$id);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['new_workspace'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $nw = dol_htmlentities($_POST['new_workspace'],ENT_QUOTES,'UTF-8');
    $r  = $db->query("SELECT MAX(position) as m FROM llx_myworkspace");
    $p  = ($r && $o=$db->fetch_object($r))?$o->m+1:0;
    $db->query("INSERT INTO llx_myworkspace(label,position) VALUES('".$db->escape($nw)."',$p)");
    
    if (isset($_POST['ajax']) || 
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false)) {
        $newId = $db->last_insert_id();
        header('Content-Type: application/json');
        echo json_encode(['id' => $newId, 'label' => $nw]);
        exit;
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rename_workspace_id'],$_POST['rename_workspace_label'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $id=(int)$_POST['rename_workspace_id'];
    $lab=$db->escape($_POST['rename_workspace_label']);
    $db->query("UPDATE llx_myworkspace SET label='$lab' WHERE rowid=$id");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_workspace_id'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $db->begin();
    monday_delete_workspace_data($db, (int) $_POST['delete_workspace_id']);
    $db->commit();
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_group_workspace_id'],$_POST['group_label'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $fw=(int)$_POST['add_group_workspace_id'];
    $lb=$db->escape($_POST['group_label']);
    $r=$db->query("SELECT MAX(position) as m FROM llx_myworkspace_group WHERE fk_workspace=$fw");
    $p=($r&&$o=$db->fetch_object($r))?$o->m+1:0;
    $db->query("INSERT INTO llx_myworkspace_group(fk_workspace,label,position) VALUES($fw,'$lb',$p)");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rename_group_id'],$_POST['group_label'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $id=(int)$_POST['rename_group_id'];
    $lb=$db->escape($_POST['group_label']);
    $db->query("UPDATE llx_myworkspace_group SET label='$lb' WHERE rowid=$id");
    exit;
}

// Dupliquer un groupe avec ses colonnes
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['duplicate_group_id'],$_POST['new_group_label'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    
    $oldGroupId = (int)$_POST['duplicate_group_id'];
    $newLabel = $db->escape($_POST['new_group_label']);
    
    // Récupérer le groupe original
    $res = $db->query("SELECT fk_workspace, task_column_label FROM llx_myworkspace_group WHERE rowid = $oldGroupId");
    if (!$o = $db->fetch_object($res)) {
        http_response_code(404);
        exit;
    }
    
    $workspaceId = $o->fk_workspace;
    $taskColumnLabel = $o->task_column_label;
    
    // Récupérer la position max
    $res = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_group WHERE fk_workspace = $workspaceId");
    $p = ($res && $row = $db->fetch_object($res)) ? $row->m + 1 : 0;
    
    // Créer le nouveau groupe
    $db->query("INSERT INTO llx_myworkspace_group (fk_workspace, label, position, task_column_label) 
               VALUES ($workspaceId, '$newLabel', $p, '".$db->escape($taskColumnLabel)."')");
    
    $newGroupId = $db->last_insert_id('llx_myworkspace_group');
    
    // Copier les colonnes du groupe original
    $resColumns = $db->query("SELECT rowid, label, type FROM llx_myworkspace_column WHERE fk_group = $oldGroupId ORDER BY position ASC");
    
    if ($resColumns) {
        // Récupérer toutes les colonnes d'abord
        $columns = [];
        while ($col = $db->fetch_object($resColumns)) {
            $columns[] = $col;
        }
        
        // Ensuite les traiter (évite les problèmes de curseur)
        foreach ($columns as $col) {
            $colLabel = $db->escape($col->label);
            $colType = $db->escape($col->type);
            
            // Obtenir la position max des colonnes du nouveau groupe
            $resPos = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_column WHERE fk_group = $newGroupId");
            $colPos = 0;
            if ($resPos && $rowPos = $db->fetch_object($resPos)) {
                $colPos = (int)$rowPos->m + 1;
            }
            
            // Insérer la colonne
            $db->query("INSERT INTO llx_myworkspace_column (fk_workspace, fk_group, label, type, position) 
                       VALUES ($workspaceId, $newGroupId, '$colLabel', '$colType', $colPos)");
            
            $newColId = $db->last_insert_id('llx_myworkspace_column');
            
            // Copier les options de cette colonne
            $resOptions = $db->query("SELECT label, color FROM llx_myworkspace_column_option WHERE fk_column = ".$col->rowid." ORDER BY position ASC");
            if ($resOptions) {
                $options = [];
                while ($opt = $db->fetch_object($resOptions)) {
                    $options[] = $opt;
                }
                
                foreach ($options as $opt) {
                    $optLabel = $db->escape($opt->label);
                    $optColor = $db->escape($opt->color);
                    
                    // Obtenir position max
                    $resOptPos = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_column_option WHERE fk_column = $newColId");
                    $optPos = 0;
                    if ($resOptPos && $rowOptPos = $db->fetch_object($resOptPos)) {
                        $optPos = (int)$rowOptPos->m + 1;
                    }
                    
                    $db->query("INSERT INTO llx_myworkspace_column_option (fk_column, label, color, position) 
                               VALUES ($newColId, '$optLabel', '$optColor', $optPos)");
                }
            }
        }
    }
    
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_group_id'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $db->begin();
    monday_delete_group_data($db, (int) $_POST['delete_group_id']);
    $db->commit();
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_task_column_label'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $gid = (int)$_POST['update_task_column_label'];
    $label = $db->escape($_POST['task_column_label']);
    $db->query("UPDATE llx_myworkspace_group SET task_column_label='$label' WHERE rowid=$gid");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['columns_group_id'])) {
    $gid = (int)$_GET['columns_group_id'];
    $res = $db->query("SELECT rowid, label, type FROM llx_myworkspace_column WHERE fk_group = $gid ORDER BY position ASC");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[] = ['id'=>$o->rowid,'label'=>$o->label,'type'=>$o->type];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_column_group_id'], $_POST['column_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $gid   = (int)$_POST['add_column_group_id'];
    $label = $db->escape($_POST['column_label']);
    $type  = isset($_POST['column_type']) ? $db->escape($_POST['column_type']) : 'text';
    $res = $db->query("SELECT fk_workspace FROM llx_myworkspace_group WHERE rowid = $gid");
    $ws = $db->fetch_object($res);
    $fk_workspace = $ws ? (int)$ws->fk_workspace : 0;
    $r     = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_column WHERE fk_group=$gid");
    $p     = ($r && $o=$db->fetch_object($r)) ? $o->m+1 : 0;
    $db->query("INSERT INTO llx_myworkspace_column (fk_workspace, fk_group, label, position, type) VALUES ($fk_workspace, $gid, '$label', $p, '$type')");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['column_options'])) {
    $cid = (int)$_GET['column_options'];
    $res = $db->query("SELECT rowid, label, color FROM llx_myworkspace_column_option WHERE fk_column = $cid ORDER BY position ASC");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[] = ['id'=>$o->rowid,'label'=>$o->label,'color'=>$o->color];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

// Nouvel endpoint : retourne TOUTES les options de TOUTES les colonnes
if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['all_column_options'])) {
    $res = $db->query("SELECT fk_column, rowid, label, color FROM llx_myworkspace_column_option ORDER BY fk_column ASC, position ASC");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        if (!isset($out[$o->fk_column])) {
            $out[$o->fk_column] = [];
        }
        $out[$o->fk_column][] = ['id'=>$o->rowid,'label'=>$o->label,'color'=>$o->color];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['column_info'])) {
    $cid = (int)$_GET['column_info'];
    $res = $db->query("SELECT rowid, label, type FROM llx_myworkspace_column WHERE rowid = $cid");
    if ($o = $db->fetch_object($res)) {
        header('Content-Type: application/json');
        echo json_encode(['id'=>$o->rowid,'label'=>$o->label,'type'=>$o->type]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error'=>'Column not found']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_option_column_id'], $_POST['option_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $cid   = (int)$_POST['add_option_column_id'];
    $label = $db->escape($_POST['option_label']);
    $color = isset($_POST['option_color']) ? $db->escape($_POST['option_color']) : '#cccccc';
    
    $existing = $db->query("SELECT rowid FROM llx_myworkspace_column_option WHERE fk_column = $cid AND label = '$label'");
    if ($db->num_rows($existing) > 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Une option avec ce nom existe déjà']);
        exit;
    }
    
    $r = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_column_option WHERE fk_column=$cid");
    $p = ($r && $o=$db->fetch_object($r)) ? $o->m+1 : 0;
    $db->query("INSERT INTO llx_myworkspace_column_option (fk_column,label,color,position) VALUES ($cid,'$label','$color',$p)");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rename_option_id'], $_POST['rename_option_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $oid   = (int)$_POST['rename_option_id'];
    $label = $db->escape($_POST['rename_option_label']);
    
    $res = $db->query("SELECT fk_column FROM llx_myworkspace_column_option WHERE rowid = $oid");
    $opt = $db->fetch_object($res);
    if ($opt) {
        $existing = $db->query("SELECT rowid FROM llx_myworkspace_column_option WHERE fk_column = {$opt->fk_column} AND label = '$label' AND rowid != $oid");
        if ($db->num_rows($existing) > 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Une option avec ce nom existe déjà']);
            exit;
        }
    }
    
    $db->query("UPDATE llx_myworkspace_column_option SET label='$label' WHERE rowid=$oid");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_option_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $oid = (int)$_POST['delete_option_id'];
    $db->query("DELETE FROM llx_myworkspace_column_option WHERE rowid=$oid");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_option_color'], $_POST['option_color'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $oid   = (int)$_POST['update_option_color'];
    $color = $db->escape($_POST['option_color']);
    $db->query("UPDATE llx_myworkspace_column_option SET color='$color' WHERE rowid=$oid");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rename_column_id'], $_POST['rename_column_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid   = (int)$_POST['rename_column_id'];
    $label = $db->escape($_POST['rename_column_label']);
    $db->query("UPDATE llx_myworkspace_column SET label='$label' WHERE rowid=$tid");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_column_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid = (int)$_POST['delete_column_id'];
    $db->begin();
    monday_delete_column_data($db, $tid);
    $db->commit();
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_columns'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_columns'], true);
    foreach ($order as $i=>$tid) {
        $db->query("UPDATE llx_myworkspace_column SET position=$i WHERE rowid=".(int)$tid);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_tasks_columns'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_tasks_columns'], true);
    foreach ($order as $i=>$id) {
        $db->query("UPDATE llx_myworkspace_task SET fk_column=".(int)$id." WHERE rowid=".(int)$id);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['task_cells'])) {
    $tid = (int)$_GET['task_cells'];
    $res = $db->query("SELECT fk_column, value FROM llx_myworkspace_cell WHERE fk_task = $tid");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[$o->fk_column] = $o->value;
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_cell_task'], $_POST['save_cell_column'], $_POST['save_cell_value'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid = (int)$_POST['save_cell_task'];
    $cid = (int)$_POST['save_cell_column'];
    $val = $db->escape($_POST['save_cell_value']);
    
    $db->query("INSERT INTO llx_myworkspace_cell (fk_task, fk_column, value) VALUES ($tid, $cid, '$val')
                ON DUPLICATE KEY UPDATE value = '$val'");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['task_comments'])) {
    $tid = (int)$_GET['task_comments'];
    $res = $db->query("
        SELECT c.rowid, c.comment, c.font_family, c.font_size, c.font_weight, c.font_color, c.datec, c.fk_user, u.firstname, u.lastname 
        FROM llx_myworkspace_comment c
        LEFT JOIN llx_user u ON u.rowid = c.fk_user
        WHERE c.fk_task = $tid 
        ORDER BY c.datec DESC
    ");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[] = [
            'id' => $o->rowid,
            'comment' => $o->comment,
            'font_family' => $o->font_family ?: 'Arial',
            'font_size' => (int)$o->font_size ?: 14,
            'font_weight' => (int)$o->font_weight ?: 400,
            'font_color' => $o->font_color ?: '#000000',
            'date' => $o->datec,
            'user_id' => $o->fk_user,
            'user_name' => trim($o->firstname . ' ' . $o->lastname) ?: 'Utilisateur'
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_comment_task'], $_POST['comment_text'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid = (int)$_POST['add_comment_task'];
    $comment = $db->escape($_POST['comment_text']);
    $uid = $user->id;
    $date = date('Y-m-d H:i:s');
    
    // Paramètres de formatage optionnels
    $font_family = isset($_POST['font_family']) ? $db->escape($_POST['font_family']) : 'Arial';
    $font_size = isset($_POST['font_size']) ? (int)$_POST['font_size'] : 14;
    $font_weight = isset($_POST['font_weight']) ? (int)$_POST['font_weight'] : 400;
    $font_color = isset($_POST['font_color']) ? $db->escape($_POST['font_color']) : '#000000';
    
    $sql = "INSERT INTO llx_myworkspace_comment (fk_task, fk_user, comment, font_family, font_size, font_weight, font_color, datec) 
            VALUES ($tid, $uid, '$comment', '$font_family', $font_size, $font_weight, '$font_color', '$date')";
    $result = $db->query($sql);
    
    if (!$result) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Erreur lors de l\'insertion du commentaire']);
        exit;
    }
    
    $new_id = $db->last_insert_id('llx_myworkspace_comment');
    if (!$new_id) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Impossible de récupérer l\'ID du commentaire']);
        exit;
    }
    
    $res = $db->query("
        SELECT c.rowid, c.comment, c.font_family, c.font_size, c.font_weight, c.font_color, c.datec, c.fk_user, u.firstname, u.lastname 
        FROM llx_myworkspace_comment c
        LEFT JOIN llx_user u ON u.rowid = c.fk_user
        WHERE c.rowid = $new_id
    ");
    
    $comment_data = $db->fetch_object($res);
    if (!$comment_data) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Commentaire créé mais impossible de le récupérer']);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'id' => $comment_data->rowid,
        'comment' => $comment_data->comment,
        'date' => $comment_data->datec,
        'user_id' => $comment_data->fk_user,
        'user_name' => trim($comment_data->firstname . ' ' . $comment_data->lastname) ?: 'Utilisateur'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_comment_id'], $_POST['edit_comment_text'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $cid = (int)$_POST['edit_comment_id'];
    $comment = $db->escape($_POST['edit_comment_text']);
    $uid = $user->id;
    
    $res = $db->query("SELECT fk_user FROM llx_myworkspace_comment WHERE rowid = $cid");
    $owner = $db->fetch_object($res);
    
    if ($owner && $owner->fk_user == $uid) {
        $db->query("UPDATE llx_myworkspace_comment SET comment = '$comment' WHERE rowid = $cid");
        echo 'OK';
    } else {
        http_response_code(403);
        echo 'Accès refusé';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_comment_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $cid = (int)$_POST['delete_comment_id'];
    $uid = $user->id;
    
    $res = $db->query("SELECT fk_user FROM llx_myworkspace_comment WHERE rowid = $cid");
    $owner = $db->fetch_object($res);
    
    if ($owner && $owner->fk_user == $uid) {
        $db->query("DELETE FROM llx_myworkspace_comment_file WHERE fk_comment = $cid");
        $db->query("DELETE FROM llx_myworkspace_comment WHERE rowid = $cid");
        echo 'OK';
    } else {
        http_response_code(403);
        echo 'Accès refusé';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['task_details'])) {
    $tid = (int)$_GET['task_details'];
    $res = $db->query("
        SELECT t.rowid, t.label, t.datec, g.label as group_label
        FROM llx_myworkspace_task t
        LEFT JOIN llx_myworkspace_group g ON g.rowid = t.fk_group
        WHERE t.rowid = $tid
    ");
    
    if ($task = $db->fetch_object($res)) {
        header('Content-Type: application/json');
        echo json_encode([
            'id' => $task->rowid,
            'label' => $task->label,
            'datec' => $task->datec,
            'group_label' => $task->group_label
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Tâche non trouvée']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['users_list'])) {
    $res = $db->query("
        SELECT u.rowid, u.firstname, u.lastname, u.login, u.email
        FROM llx_user u
        WHERE u.statut = 1
        ORDER BY u.firstname ASC, u.lastname ASC
    ");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $fullname = trim($o->firstname . ' ' . $o->lastname);
        if (empty($fullname)) $fullname = $o->login;
        
        $out[] = [
            'id' => $o->rowid,
            'name' => $fullname,
            'login' => $o->login,
            'email' => $o->email
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['upload_task_file'], $_FILES['task_file'])) {
    error_log("=== UPLOAD TASK FILE DEBUG ===");
    error_log("Token POST: " . $_POST['token']);
    error_log("Token SESSION: " . $_SESSION['newtoken']);
    error_log("Task ID: " . $_POST['upload_task_file']);
    error_log("File info: " . print_r($_FILES['task_file'], true));
    
    if ($_POST['token'] !== $_SESSION['newtoken']) {
        error_log("CSRF token mismatch!");
        accessforbidden('CSRF token invalid');
    }
    
    $task_id = (int)$_POST['upload_task_file'];
    $upload_dir = '/var/www/documents/myworkspace/tasks/';
    error_log("Upload dir: " . $upload_dir);
    
    if (!file_exists($upload_dir)) {
        error_log("Creating upload directory...");
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['task_file'];
    $filename = basename($file['name']);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    error_log("Filename: " . $filename . ", Extension: " . $extension);
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
    if (!in_array($extension, $allowed_extensions)) {
        error_log("Extension not allowed: " . $extension);
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Type de fichier non autorisé']);
        exit;
    }
    
    if ($file['size'] > 10 * 1024 * 1024) {
        error_log("File too large: " . $file['size']);
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier trop volumineux (max 10MB)']);
        exit;
    }
    
    $unique_filename = time() . '_' . uniqid() . '_' . $filename;
    $filepath = $upload_dir . $unique_filename;
    error_log("Target filepath: " . $filepath);
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        error_log("File moved successfully");
        $original_name = $db->escape($filename);
        $unique_name = $db->escape($unique_filename);
        $filesize = (int)$file['size'];
        $mimetype = $db->escape($file['type']);
        $uid = $user->id;
        $date = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO llx_myworkspace_task_file (fk_task, original_name, filename, filesize, mimetype, fk_user, datec) 
                VALUES ($task_id, '$original_name', '$unique_name', $filesize, '$mimetype', $uid, '$date')";
        error_log("SQL: " . $sql);
        
        if ($db->query($sql)) {
            $file_id = $db->last_insert_id('llx_myworkspace_task_file');
            error_log("File inserted with ID: " . $file_id);
            header('Content-Type: application/json');
            echo json_encode([
                'rowid' => $file_id,
                'original_name' => $filename,
                'filename' => $unique_filename,
                'filesize' => $filesize,
                'mimetype' => $file['type']
            ]);
        } else {
            error_log("Database insert failed: " . $db->error());
            unlink($filepath);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Erreur lors de l\'enregistrement']);
        }
    } else {
        error_log("Move uploaded file failed. Upload error: " . $file['error']);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Erreur lors de l\'upload']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['task_files'])) {
    $task_id = (int)$_GET['task_files'];
    $res = $db->query("
        SELECT f.rowid, f.original_name, f.filename, f.filesize, f.mimetype, f.datec, u.firstname, u.lastname
        FROM llx_myworkspace_task_file f
        LEFT JOIN llx_user u ON u.rowid = f.fk_user
        WHERE f.fk_task = $task_id
        ORDER BY f.datec ASC
    ");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[] = [
            'rowid' => $o->rowid,
            'original_name' => $o->original_name,
            'filename' => $o->filename,
            'filesize' => $o->filesize,
            'mimetype' => $o->mimetype,
            'date' => $o->datec,
            'user_name' => trim($o->firstname . ' ' . $o->lastname) ?: 'Utilisateur'
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['download_file'])) {
    $file_id = (int)$_GET['download_file'];
    $type = isset($_GET['type']) ? $_GET['type'] : 'comment';
    
    if ($type === 'task') {
        $res = $db->query("SELECT original_name, filename, mimetype FROM llx_myworkspace_task_file WHERE rowid = $file_id");
        $subdir = 'tasks';
    } else {
        $res = $db->query("SELECT original_name, filename, mimetype FROM llx_myworkspace_comment_file WHERE rowid = $file_id");
        $subdir = 'comments';
    }
    
    if ($file = $db->fetch_object($res)) {
        $filepath = '/var/www/documents/myworkspace/'.$subdir.'/' . $file->filename;
        
        if (file_exists($filepath)) {
            header('Content-Type: ' . $file->mimetype);
            header('Content-Disposition: inline; filename="' . $file->original_name . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
        } else {
            http_response_code(404);
            echo 'Fichier non trouvé';
        }
    } else {
        http_response_code(404);
        echo 'Fichier non trouvé';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_file_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $file_id = (int)$_POST['delete_file_id'];
    $type = isset($_POST['type']) ? $_POST['type'] : 'comment';
    $uid = $user->id;
    
    if ($type === 'task') {
        $res = $db->query("SELECT filename, fk_user FROM llx_myworkspace_task_file WHERE rowid = $file_id");
        $subdir = 'tasks';
        $table = 'llx_myworkspace_task_file';
    } else {
        $res = $db->query("SELECT filename, fk_user FROM llx_myworkspace_comment_file WHERE rowid = $file_id");
        $subdir = 'comments';
        $table = 'llx_myworkspace_comment_file';
    }
    
    $file = $db->fetch_object($res);
    
    if ($file && $file->fk_user == $uid) {
        $filepath = '/var/www/documents/myworkspace/'.$subdir.'/' . $file->filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        $db->query("DELETE FROM $table WHERE rowid = $file_id");
        echo 'OK';
    } else {
        http_response_code(403);
        echo 'Accès refusé';
    }
    exit;
}

$res = $db->query("SELECT rowid,label FROM llx_myworkspace ORDER BY position ASC");
$workspaces = [];
while ($res && $o=$db->fetch_object($res)) $workspaces[] = $o;

llxHeader("", "Planity - Mes espaces", "");
$formtoken = newToken();

$leftmenu = '<h3>Espaces de travail</h3>'
    . '<form method="POST" style="margin:10px 0;">'
    . '<input name="new_workspace" placeholder="Nouvel espace" required style="width:70%;cursor:pointer;">'
    . "<input type=\"hidden\" name=\"token\" value=\"$formtoken\">"
    . '<button type="submit" class="add-workspace-btn">+</button>'
    . '</form>'
    . '<ul id="workspace-list" style="list-style:none;padding:0;">';
foreach ($workspaces as $w) {
    $leftmenu .= '<li class="workspace-item" data-id="'.$w->rowid.'">'
               . dol_escape_htmltag($w->label)
               . '</li>';
}
$leftmenu .= '</ul>'
    . '<div class="workspace-kpi-entry" id="kpi-dashboard-link">Tableaux KPI</div>';

ob_start();
?>
<link rel="stylesheet" href="<?php echo DOL_URL_ROOT ?>/custom/monday/css/main.css?v=<?php echo time(); ?>&popup=1">

<div class="workspace-container">
  <div class="main-content" id="main-content"></div>
  
  <div id="task-detail-panel" class="task-detail-panel">
    <div class="panel-header">
      <h3 id="task-detail-title">Détail</h3>
      <button id="close-panel" class="close-panel-btn">×</button>
    </div>
    
    <div class="panel-content">
      <div class="task-info-section">
        <h4>Informations</h4>
        <div class="task-meta">
          <div class="task-meta-item">
            <strong id="task-label-text">Tâche :</strong>
            <span id="task-name-display"></span>
            <button id="edit-task-name" class="edit-btn">✎</button>
            <button id="delete-task-from-panel" class="delete-btn" style="margin-left: 5px;">✖</button>
          </div>
          <div class="task-meta-item">
            <strong>Groupe :</strong>
            <span id="task-group-display"></span>
          </div>
          <div class="task-meta-item">
            <strong>Créée :</strong>
            <span id="task-created-display"></span>
          </div>
        </div>
      </div>
      
      <div class="comments-section">
        <h4>Commentaires</h4>
        
        <div class="add-comment-form">
          <div class="comment-formatting-toolbar">
            <button id="comment-bold-toggle" class="format-btn" title="Gras">
              <strong>G</strong>
            </button>
            <button id="comment-italic-toggle" class="format-btn" title="Italique">
              <em>I</em>
            </button>
            <input type="color" id="comment-color" value="#000000" title="Couleur">
          </div>
          <div id="new-comment-text" class="comment-editor" contenteditable="true" placeholder="Ajouter un commentaire..."></div>
          <button id="add-comment-btn">Publier</button>
        </div>
        
        <div id="comments-list" class="comments-list">
        </div>
      </div>
      
      <div class="task-files-section">
        <h4>Fichiers de la tâche</h4>
        
        <div class="task-files-content">
          <div class="task-file-upload-area">
            <input type="file" id="task-file-input" style="display:none;" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
            <button id="add-task-file-btn">📎 Ajouter des fichiers</button>
          </div>
          
          <div id="task-files-list" class="task-files-list">
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
window.leftmenu = <?php echo json_encode($leftmenu); ?>;
window.formtoken = <?php echo json_encode($formtoken); ?>;
window.userId = <?php echo $user->id; ?>;
</script>
<script src="<?php echo DOL_URL_ROOT ?>/custom/monday/js/main.js?v=<?php echo time(); ?>"></script>

<?php
echo ob_get_clean();
llxFooter();
$db->close();
?>
