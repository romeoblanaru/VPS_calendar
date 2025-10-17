<?php
// Include this file in every page to show navigation bar
?>

<?php if (isset($_SESSION['alert'])): ?>
<div class="alert alert-<?php echo $_SESSION['alert']['type']; ?> position-fixed top-0 start-50 translate-middle-x mt-3 z-3" style="min-width: 300px; z-index:9999;" role="alert" id="alertBox">
  <?php echo $_SESSION['alert']['message']; ?>
</div>
<script>
  setTimeout(() => {
    const alertBox = document.getElementById('alertBox');
    if (alertBox) alertBox.remove();
  }, 5000);
</script>
<?php unset($_SESSION['alert']); endif; ?>