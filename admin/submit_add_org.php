<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("INSERT INTO organisations (
            user, pasword, contact_name, position,
            oficial_company_name, alias_name, company_head_office_address,
            company_phone_nr, owner_name, owner_phone_nr, email_address,
            www_address, country
        ) VALUES (
            :user, :pasword, :contact_name, :position,
            :oficial_company_name, :alias_name, :company_head_office_address,
            :company_phone_nr, :owner_name, :owner_phone_nr, :email_address,
            :www_address, :country
        )");

        $stmt->execute([
            ':user' => $_POST['user'],
            ':pasword' => $_POST['pasword'],
            ':contact_name' => $_POST['contact_name'],
            ':position' => $_POST['position'],
            ':oficial_company_name' => $_POST['oficial_company_name'],
            ':alias_name' => $_POST['alias_name'],
            ':company_head_office_address' => $_POST['company_head_office_address'],
            ':company_phone_nr' => $_POST['company_phone_nr'],
            ':owner_name' => $_POST['owner_name'],
            ':owner_phone_nr' => $_POST['owner_phone_nr'],
            ':email_address' => $_POST['email_address'],
            ':www_address' => $_POST['www_address'],
            ':country' => $_POST['country']
        ]);

        echo "<div class='alert alert-success'>Organisation successfully added.</div>";
        echo "<script>
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('actionModal'));
                modal.hide();
                location.reload();
            }, 2000);
        </script>";

    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}
?>
