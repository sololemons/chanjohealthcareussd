<?php
// ussd.php

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "chanjohealthcare";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Capture the incoming request parameters from Africa's Talking
$sessionId = $_POST['sessionId'];
$serviceCode = $_POST['serviceCode'];
$phoneNumber = $_POST['phoneNumber'];
$text = $_POST['text'];

// Parse the text input
$inputArray = explode("*", $text);
$userResponse = trim(end($inputArray));

// Function to display menu
function displayMenu($menu) {
    echo "CON $menu";
    exit;
}

// Function to end the session
function endSession($message) {
    echo "END $message";
    exit;
}

// Main logic
if ($text == "") {
    // Initial Menu
    $menu = "Welcome to ChanjoHealth.\n";
    $menu .= "1. Register\n";
    $menu .= "2. Signup";
    displayMenu($menu);
} else {
    switch ($inputArray[0]) {
        case "1":
            // Register
            if (count($inputArray) == 1) {
                $menu = "Enter Username:";
                displayMenu($menu);
            } elseif (count($inputArray) == 2) {
                $menu = "Enter Password:";
                displayMenu($menu);
            } else {
                $username = $inputArray[1];
                $password = md5($inputArray[2]); // Store the md5 hash in a variable
                
                // Check user credentials
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND password = ?");
                $stmt->bind_param("ss", $username, $password); // Pass the variable
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    // Successful login
                    $stmt->bind_result($userId);
                    $stmt->fetch();
                    $menu = "1. Add New Child\n";
                    $menu .= "2. View My Children\n";
                    $menu .= "3. View Immunization Schedule";
                    displayMenu($menu);
                } else {
                    // Login failed
                    endSession("Invalid credentials. Please try again.");
                }
                $stmt->close();
            }
            break;

        case "2":
            // Signup
            if (count($inputArray) == 1) {
                $menu = "Enter Username:";
                displayMenu($menu);
            } elseif (count($inputArray) == 2) {
                $menu = "Enter Password:";
                displayMenu($menu);
            } elseif (count($inputArray) == 3) {
                $menu = "Confirm Password:";
                displayMenu($menu);
            } else {
                $username = $inputArray[1];
                $password = $inputArray[2];
                $confirmPassword = $inputArray[3];
                
                if ($password === $confirmPassword) {
                    // Create new user
                    $passwordHash = md5($password); // Store the md5 hash in a variable
                    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                    $stmt->bind_param("ss", $username, $passwordHash); // Pass the variable
                    
                    if ($stmt->execute()) {
                        endSession("Account created successfully. You can now register your children.");
                    } else {
                        endSession("Error creating account. Username may already exist.");
                    }
                    $stmt->close();
                } else {
                    endSession("Passwords do not match. Please try again.");
                }
            }
            break;

        case "1*1":
            // Add New Child
            if (count($inputArray) == 3) {
                $menu = "Enter Child's Name:";
                displayMenu($menu);
            } elseif (count($inputArray) == 4) {
                $menu = "Enter Child's Birth Date (YYYY-MM-DD):";
                displayMenu($menu);
            } else {
                $childName = $inputArray[3];
                $birthDate = $inputArray[4];

                // Get userId from login (you might need to store this in a session or similar)
                $userId = $inputArray[1];
                
                // Register new child
                $stmt = $conn->prepare("INSERT INTO children (user_id, name, birth_date) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $userId, $childName, $birthDate);
                
                if ($stmt->execute()) {
                    // Calculate immunization dates and store them
                    $childId = $stmt->insert_id;
                    $immunizationSchedule = [
                        ['name' => 'BCG', 'days' => 0],
                        ['name' => 'Polio 1', 'days' => 42],
                        ['name' => 'Polio 2', 'days' => 70],
                        ['name' => 'Polio 3', 'days' => 98],
                        // Add more immunizations as needed
                    ];
                    $stmt->close();
                    
                    $stmt = $conn->prepare("INSERT INTO immunizations (child_id, immunization_date, immunization_name) VALUES (?, ?, ?)");
                    foreach ($immunizationSchedule as $immunization) {
                        $immunizationDate = date('Y-m-d', strtotime($birthDate . " + {$immunization['days']} days"));
                        $stmt->bind_param("iss", $childId, $immunizationDate, $immunization['name']);
                        $stmt->execute();
                    }
                    $stmt->close();
                    
                    endSession("Child registered successfully and immunization schedule created.");
                } else {
                    endSession("Error registering child. Please try again.");
                }
                $stmt->close();
            }
            break;

        case "1*2":
            // View My Children
            // Get userId from login (you might need to store this in a session or similar)
            $userId = $inputArray[1];
            
            $stmt = $conn->prepare("SELECT id, name, birth_date FROM children WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->bind_result($childId, $childName, $birthDate);
            
            $children = "Your children:\n";
            while ($stmt->fetch()) {
                $children .= "$childName (DOB: $birthDate)\n";
            }
            
            endSession($children);
            $stmt->close();
            break;

        case "1*3":
            // View Immunization Schedule
            if (count($inputArray) == 2) {
                $menu = "Enter Child's Name:";
                displayMenu($menu);
            } else {
                $childName = $inputArray[2];
                $userId = $inputArray[1];
                
                // Get child ID
                $stmt = $conn->prepare("SELECT id FROM children WHERE user_id = ? AND name = ?");
                $stmt->bind_param("is", $userId, $childName);
                $stmt->execute();
                $stmt->bind_result($childId);
                if ($stmt->fetch()) {
                    $stmt->close();
                    
                    // Get immunization schedule
                    $stmt = $conn->prepare("SELECT immunization_name, immunization_date, status FROM immunizations WHERE child_id = ?");
                    $stmt->bind_param("i", $childId);
                    $stmt->execute();
                    $stmt->bind_result($immunizationName, $immunizationDate, $status);
                    
                    $schedule = "Immunization schedule for $childName:\n";
                    while ($stmt->fetch()) {
                        $schedule .= "$immunizationName: $immunizationDate ($status)\n";
                    }
                    
                    endSession($schedule);
                } else {
                    endSession("Child not found. Please check the name and try again.");
                }
                $stmt->close();
            }
            break;

        default:
            endSession("Invalid input. Please try again.");
            break;
    }
}

$conn->close();
?>
