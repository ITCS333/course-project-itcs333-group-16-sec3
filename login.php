<?php
// Database connection
$host = 'localhost';
$db   = 'course';
$user = 'admin';
$pass = 'password123';
$dsn = "mysql:host=$host;dbname=$db;";

try {
    $pdo = new PDO($dsn, $user, $pass);
} catch (\PDOException $e) {
    die("Database connection failed.");
}

$error = "";

// When form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['username'];
    $password = $_POST['password'];

    // Prepared statement
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userData && $password === "password") {  
        // Login success
        session_start();
        $_SESSION['user'] = $userData;

        if ($userData['role'] === 'Admin') {
            header("Location: admin_dashboard.php");
            exit;
        } else {
            header("Location: student_dashboard.php");
            exit;
        }
    } else {
        $error = "Incorrect email or password!";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Web Development Course</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f4f8;
            color: #333;
            line-height: 1.6;
        }
        header {
            background-color: #1e3a8a;
            color: #fff;
            padding: 30px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        header h1 { font-size: 2rem; }
        header .login-btn {
            background-color: #3b82f6;
            color: #fff;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: 0.3s;
        }
        header .login-btn:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
        }
        main {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 70vh;
        }
        .login-section {
            background-color: #fff;
            padding: 30px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-section h2 {
            color: #1e3a8a;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        .login-form { display: flex; flex-direction: column; gap: 15px; }
        .login-form label { text-align: left; font-weight: 600; color: #1e40af; }
        .login-form input {
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1rem;
        }
        .login-form button {
            padding: 12px;
            background-color: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .login-form button:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
        }
        footer {
            text-align: center;
            padding: 20px;
            background-color: #1e3a8a;
            color: #fff;
            margin-top: 50px;
        }
        .error {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<header>
    <h1>Web Development Course</h1>
    <a href="index.html" class="login-btn">Home</a>
</header>

<main>
    <section class="login-section">
        <h2>Login</h2>

        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="login.php" method="post" class="login-form">
            <label for="username">Email / Username:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
        </form>
    </section>
</main>

<footer>
    &copy; 2025 Web Development Course. All Rights Reserved.
</footer>

</body>
</html>
