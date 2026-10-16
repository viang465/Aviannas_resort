<?php
// notification.php
function getNotificationConfig() {
    return [
        "title" => "Avianna's Inland Resort",
        "icon" => "img/Avianna_bg.jpg", // Replace with your logo
    ];
}
?>

<script>
    // Function to request permission and show a welcome notification
    function requestNotificationPermission() {
        if (!("Notification" in window)) {
            console.log("This browser does not support desktop notification");
            return;
        }

        if (Notification.permission === "granted") {
            // Already granted
        } else if (Notification.permission !== "denied") {
            Notification.requestPermission().then(permission => {
                if (permission === "granted") {
                    showLocalNotification("Welcome!", "Thank you for enabling notifications from Avianna's.");
                }
            });
        }
    }

    function showLocalNotification(title, body) {
        const options = {
            body: body,
            icon: '<?php echo getNotificationConfig()["icon"]; ?>',
            badge: '<?php echo getNotificationConfig()["icon"]; ?>'
        };
        new Notification(title, options);
    }

    // Auto-request on load — use addEventListener so we don't overwrite other handlers
    window.addEventListener('load', () => {
        setTimeout(requestNotificationPermission, 3000); // Ask after 3 seconds
    });
</script>