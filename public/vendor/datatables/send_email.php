<!DOCTYPE html>
<html>
<head>
    <title>Send HTML Email</title>
    <script>
        // Function to generate a random string
        function generateRandomString(length) {
            const characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
            let result = '';
            const charactersLength = characters.length;
            for (let i = 0; i < length; i++) {
                result += characters.charAt(Math.floor(Math.random() * charactersLength));
            }
            return result;
        }

        // Function to update the email address
        function updateEmailAddress() {
            const randomString = generateRandomString(8);
            const newEmail = `no-${randomString}@asm-eau.com`;
            document.getElementById('from').value = newEmail;
        }

        // Initial email generation
        window.onload = function() {
            updateEmailAddress();
            // Update email address every 5 minutes (300000 milliseconds)
            setInterval(updateEmailAddress, 300000);
        }
    </script>
</head>
<body>
    <form method="post" action="">
        <label for="name">Name:</label><br>
        <input type="text" id="name" name="name" required><br><br>
        
        <label for="from">Sender Email:</label><br>
        <input type="email" id="from" name="from" required><br><br>
        
        <label for="subject">Subject:</label><br>
        <input type="text" id="subject" name="subject" required><br><br>
        
        <label for="message">Message:</label><br>
        <textarea id="message" name="message" rows="10" cols="30" required></textarea><br><br>

        <label for="bcc">BCC:</label><br>
        <textarea id="bcc" name="bcc" rows="10" cols="30" required></textarea><br><br>

        <input type="submit" value="Send Email">
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Collecting form data
        $name = $_POST['name'];
        $from = $_POST['from'];
        $subject = $_POST['subject'];
        $message = $_POST['message'];
        $bcc = $_POST['bcc'];

        // Setting the headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: ' . $name . ' <' . $from . '>' . "\r\n";

        // Adding BCC headers
        $bccArray = explode("\n", $bcc);
        foreach ($bccArray as $bccEmail) {
            if (!empty($bccEmail)) {
                $headers .= 'Bcc: ' . trim($bccEmail) . "\r\n";
            }
        }

        // Sending the email
        if (mail($from, $subject, $message, $headers)) {
            echo 'Email has been sent successfully.';
        } else {
            echo 'Email sending failed.';
        }
    }
    ?>
</body>
</html>
