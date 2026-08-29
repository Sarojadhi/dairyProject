<?php
session_start();
require_once '../includes/config.php';
requireLogin();

if (isset($_POST['add'])) {
    $code = $_POST['code'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO farmers (code, name, phone, address, password)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssss", $code, $name, $phone, $address, $password);
    $stmt->execute();
}

if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $stmt = $conn->prepare(
        "UPDATE farmers SET name=?, phone=?, address=? WHERE id=?"
    );
    $stmt->bind_param("sssi", $name, $phone, $address, $id);
    $stmt->execute();
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM farmers WHERE id=$id");
}

$farmers = $conn->query("SELECT * FROM farmers");
?>

<h2>Farmers</h2>

<h3>Add Farmer</h3>

<form method="POST">
    <input name="code" placeholder="Farmer Code" required>
    <input name="name" placeholder="Name" required>
    <input name="phone" placeholder="Phone">
    <input name="address" placeholder="Address">
    <input type="password" name="password" placeholder="Password" required>
    <button name="add">Add</button>
</form>

<h3>Farmers List</h3>

<table border="1">
    <tr>
        <th>ID</th>
        <th>Code</th>
        <th>Name</th>
        <th>Phone</th>
        <th>Address</th>
        <th>Actions</th>
    </tr>

    <?php while ($farmer = $farmers->fetch_assoc()): ?>
    <tr>
        <td><?= $farmer['id'] ?></td>
        <td><?= $farmer['code'] ?></td>
        <td><?= $farmer['name'] ?></td>
        <td><?= $farmer['phone'] ?></td>
        <td><?= $farmer['address'] ?></td>
        <td>
            <a href="?delete=<?= $farmer['id'] ?>">Delete</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>