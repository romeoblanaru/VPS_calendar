<?php
/**
 * Shared credential validation helpers
 */

/**
 * Check if a username+password pair is unique across all auth tables.
 * Returns ['unique' => true] when no conflicts, or
 * ['unique' => false, 'table' => string, 'message' => string] on conflict.
 *
 * @param PDO $pdo
 * @param string $username
 * @param string $password
 * @param array $exclude Optional map of table => id to exclude current record when updating
 */
function checkUserPasswordUniqueness(PDO $pdo, string $username, string $password, array $exclude = []): array
{
    $tables = [
        'organisations'  => ['user_col' => 'user', 'pass_col' => 'pasword',  'id_col' => 'unic_id'],
        'specialists'    => ['user_col' => 'user', 'pass_col' => 'password', 'id_col' => 'unic_id'],
        'super_users'    => ['user_col' => 'user', 'pass_col' => 'pasword',  'id_col' => 'unic_id'],
        'working_points' => ['user_col' => 'user', 'pass_col' => 'password', 'id_col' => 'unic_id'],
    ];

    foreach ($tables as $table => $cols) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$cols['user_col']} = ? AND {$cols['pass_col']} = ?";
        $params = [$username, $password];

        if (isset($exclude[$table]) && $exclude[$table]) {
            $sql .= " AND {$cols['id_col']} <> ?";
            $params[] = $exclude[$table];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ((int)$stmt->fetchColumn() > 0) {
            return [
                'unique' => false,
                'table' => $table,
                'message' => "Username and password combination already exists in {$table} table",
            ];
        }
    }

    return ['unique' => true];
}



