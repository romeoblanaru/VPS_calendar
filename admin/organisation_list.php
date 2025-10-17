<?php
require_once __DIR__ . '/../includes/db.php';

// Fetch all organisations
$orgs = $pdo->query("SELECT * FROM organisations ORDER BY oficial_company_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Organisation List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<h4>Organisation List</h4>

<?php foreach ($orgs as $org): ?>
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <?= htmlspecialchars($org['oficial_company_name']) ?> (<?= htmlspecialchars($org['alias_name']) ?>)
        </div>
        <div class="card-body">
            <p><strong>Contact:</strong> <?= htmlspecialchars($org['contact_name']) ?> (<?= htmlspecialchars($org['email_address']) ?>)</p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($org['company_phone_nr']) ?> | <strong>Owner:</strong> <?= htmlspecialchars($org['owner_name']) ?></p>
            <h6 class="mt-3">Working Points:</h6>
            <ul>
                <?php
                $stmt = $pdo->prepare("SELECT * FROM working_points WHERE organisation_id = ?");
                $stmt->execute([$org['unic_id']]);
                $wps = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($wps as $wp):
                ?>
                    <li><strong><?= htmlspecialchars($wp['name_of_the_place']) ?>:</strong> <?= htmlspecialchars($wp['address']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endforeach; ?>

</body>
</html>
