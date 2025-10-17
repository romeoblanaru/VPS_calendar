
<form action="admin/import_csv_handler.php" method="post" enctype="multipart/form-data">
    <div class="mb-3">
        <label for="csv_file" class="form-label">Choose CSV file to upload:</label>
        <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
    </div>
    <button type="submit" class="btn btn-success">Upload</button>
</form>
