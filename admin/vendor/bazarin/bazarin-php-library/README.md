# Bazarin PHP Library - Comprehensive Guide

Bazarin PHP Library is a lightweight and efficient PHP library for database operations, file handling, API interactions, and security functions.

## 📌 Installation
To install the library using **Composer**, run:

```sh
composer require bazarin/php-library
```

After installation, include the **autoload** file in your PHP project:

```php
require 'vendor/autoload.php';
```

---

## 🚀 Usage

### 1️⃣ Database Connection & Query Builder
To interact with the database, initialize the **Connection** and **QueryBuilder** classes:

```php
use Bazarin\Database\Connection;
use Bazarin\Database\QueryBuilder;

// Database Configuration
$db = new Connection([
    'host' => 'localhost',
    'user' => 'bazarin',
    'password' => 'bazarin',
    'database' => 'xgramm'
]);

// Initialize Query Builder
$query = new QueryBuilder($db->getConnection());
```

#### 🔹 Select Query
Fetch a user where `username = 'xgramm'`:

```php
$users = $query->select("users", '*', ['username' => 'xgramm']);
print_r($users);
```

#### 🔹 Insert Query
Adding a new user:

```php
$data = [
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'password' => password_hash('securepass', PASSWORD_BCRYPT)
];

$userId = $query->insert('users', $data);
if ($userId) {
    echo "User added successfully with ID: $userId";
}
```

#### 🔹 Update Query
Updating a user's email:

```php
$updateData = ['email' => 'newemail@example.com'];
$updated = $query->update('users', $updateData, ['username' => 'john_doe']);

if ($updated) {
    echo "User email updated successfully";
}
```

#### 🔹 Delete Query
Deleting a user:

```php
$deleted = $query->delete('users', ['username' => 'john_doe']);
if ($deleted) {
    echo "User deleted successfully";
}
```

#### 🔹 Authentication
User authentication using hashed passwords:

```php
$authResult = $query->auth('users', 'john_doe', 'securepass');
print_r($authResult);
```

---

### 2️⃣ File Helper - Handling File Operations
The **FileHelper** class helps manage file uploads and operations.

```php
use Bazarin\Helpers\FileHelper;

$fileHelper = new FileHelper();
$filePath = "uploads/sample.txt";

// Check if a file exists
if ($fileHelper->exists($filePath)) {
    echo "File exists!";
}
```

---

### 3️⃣ Date Helper - Working with Dates
The **DateHelper** class provides various date-related functions:

```php
use Bazarin\Helpers\DateHelper;

$dateHelper = new DateHelper();

// Get current date
echo $dateHelper->now();
```

---

### 4️⃣ API Requests with cURL
The **Curl** class simplifies HTTP requests.

```php
use Bazarin\APIS\Curl;

$curl = new Curl();
$response = $curl->request('https://jsonplaceholder.typicode.com/todos/1', 'GET');
var_dump($response);
```

---

### 5️⃣ FileGetContent - Retrieving Remote File Content

```php
use Bazarin\APIS\FileGetContent;

$fileGetContent = new FileGetContent('*');
$content = $fileGetContent->fetch("https://example.com/sample.txt");

echo $content;
```

---

### 6️⃣ Security & Encryption

The **Cryptions** class handles cryptographic operations like encryption and decryption.

```php
use Bazarin\Security\Cryptions;

$cryptions = new Cryptions('my-secret-key');
$encrypted = $cryptions->encrypt("Hello, World!");
$decrypted = $cryptions->decrypt($encrypted);

echo "Encrypted: $encrypted\n";
echo "Decrypted: $decrypted\n";
```

