<?php
session_start();
require_once '../includes/config.php';

requireLogin();
requireRole('admin');


// ADD
if (isset($_POST['add'])) {

    $name = $_POST['name'];
    $username = $_POST['username'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("
        INSERT INTO users (name, username, password, role, phone, email)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssss",
        $name,
        $username,
        $password,
        $role,
        $phone,
        $email
    );

    $stmt->execute();
}


// UPDATE
if (isset($_POST['update'])) {

    $id = $_POST['id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $role = $_POST['role'];

    $stmt = $conn->prepare("
        UPDATE users
        SET name=?, phone=?, email=?, role=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "ssssi",
        $name,
        $phone,
        $email,
        $role,
        $id
    );

    $stmt->execute();
}


// DELETE / DEACTIVATE
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    $conn->query("
        UPDATE users
        SET is_active=0
        WHERE id=$id
    ");
}


// GET STAFF
$staff = $conn->query("
    SELECT id, name, username, role, phone, email, is_active
    FROM users
    ORDER BY name
");

?>

<?php include '../includes/header.php'; ?>

<div class="main-content">

    <h2>Manage Staff</h2>

    <!-- ADD STAFF -->

    <div class="form-card">

        <h4>Add Staff</h4>

        <form method="POST">

            <input
                type="text"
                name="name"
                class="form-control mb-2"
                placeholder="Name"
                required
            >

            <input
                type="text"
                name="username"
                class="form-control mb-2"
                placeholder="Username"
                required
            >

            <input
                type="text"
                name="phone"
                class="form-control mb-2"
                placeholder="Phone"
            >

            <input
                type="email"
                name="email"
                class="form-control mb-2"
                placeholder="Email"
            >

            <input
                type="password"
                name="password"
                class="form-control mb-2"
                placeholder="Password"
                required
            >

            <select name="role" class="form-control mb-2">
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
            </select>

            <button name="add" class="btn btn-teal">
                Add Staff
            </button>

        </form>

    </div>


    <!-- STAFF LIST -->

    <div class="data-card">

        <h4>Staff List</h4>

        <table class="dairy-table">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php while ($s = $staff->fetch_assoc()): ?>

                <tr>

                    <td><?= $s['id'] ?></td>

                    <td><?= $s['name'] ?></td>

                    <td><?= $s['username'] ?></td>

                    <td><?= $s['role'] ?></td>

                    <td><?= $s['phone'] ?></td>

                    <td><?= $s['email'] ?></td>

                    <td>
                        <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                    </td>

                    <td>

                        <a href="edit_staff.php?id=<?= $s['id'] ?>">
                            Edit
                        </a>

                        <a href="?delete=<?= $s['id'] ?>">
                            Delete
                        </a>

                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include '../includes/footer.php'; ?>