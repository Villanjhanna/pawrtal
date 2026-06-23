<?php
/* ===========================================================
   Pawrtal — Match Reminder Script
   Run manually or via cron to nudge both parties when a
   confirmed match has had no activity for 3 or 7 days.

   Usage:
     php reminders.php
     php reminders.php --dry-run   (preview without inserting)
=========================================================== */

$dry_run = in_array('--dry-run', $argv ?? []);

require_once __DIR__ . '/../config/db.php';

$now = new DateTimeImmutable();
echo "[Pawrtal Reminders] " . $now->format('Y-m-d H:i:s') . "\n";
echo $dry_run ? "[DRY RUN — no changes written]\n\n" : "\n";

// ── Fetch all matches still in 'matched' status ───────────────
$matches = $conn->query("
    SELECT m.id, m.updated_at,
           l.pet_name, l.user_id AS owner_id,
           f.id AS f_id, f.user_id AS finder_id,
           f.contact_name AS finder_name
    FROM matches m
    JOIN lost_reports  l ON m.lost_report_id  = l.id
    JOIN found_reports f ON m.found_report_id = f.id
    WHERE m.status = 'matched'
")->fetch_all(MYSQLI_ASSOC);

if (empty($matches)) {
    echo "No active confirmed matches found. Nothing to do.\n";
    exit(0);
}

$sent = 0;

foreach ($matches as $m) {
    $confirmed_at = new DateTimeImmutable($m['updated_at']);
    $days_elapsed = (int)$confirmed_at->diff($now)->days;

    $pet      = $conn->real_escape_string($m['pet_name']);
    $owner_id = (int)$m['owner_id'];
    $finder_id= (int)$m['finder_id'];
    $mid      = (int)$m['id'];
    $f_id     = (int)$m['f_id'];

    // ── Day-3 reminder ───────────────────────────────────────
    if ($days_elapsed === 3) {

        // Check we haven't already sent the day-3 reminder for this match
        $already = $conn->query("
            SELECT id FROM notifications
            WHERE user_id = $owner_id
              AND type = 'match_reminder_3'
              AND message LIKE '%match #$mid%'
            LIMIT 1
        ")->fetch_assoc();

        if (!$already) {
            echo "  Match #$mid — $days_elapsed days elapsed → sending day-3 reminders\n";

            // Owner
            $owner_msg = $conn->real_escape_string(
                "Reminder: you confirmed a match for \"$pet\" 3 days ago (match #$mid) "
              . "but it hasn't been marked as reunited yet. "
              . "Have you been able to reach the finder? Tap to view their contact details."
            );
            // Finder
            $finder_msg = $conn->real_escape_string(
                "Reminder: the owner of \"$pet\" confirmed your found report as a match 3 days ago (match #$mid). "
              . "They are trying to reach you. Please check your messages and respond as soon as you can."
            );

            if (!$dry_run) {
                $conn->query("INSERT INTO notifications (user_id,type,message,link)
                    VALUES ($owner_id, 'match_reminder_3', '$owner_msg',
                            '../dashboard/match_contact.php?id=$mid')");
                $conn->query("INSERT INTO notifications (user_id,type,message,link)
                    VALUES ($finder_id,'match_reminder_3','$finder_msg',
                            '../public/view_found.php?id=$f_id')");
            }
            $sent += 2;
        } else {
            echo "  Match #$mid — day-3 reminder already sent, skipping\n";
        }
    }

    // ── Day-7 reminder ───────────────────────────────────────
    elseif ($days_elapsed === 7) {

        $already = $conn->query("
            SELECT id FROM notifications
            WHERE user_id = $owner_id
              AND type = 'match_reminder_7'
              AND message LIKE '%match #$mid%'
            LIMIT 1
        ")->fetch_assoc();

        if (!$already) {
            echo "  Match #$mid — $days_elapsed days elapsed → sending day-7 reminders\n";

            $owner_msg = $conn->real_escape_string(
                "It's been 7 days since you confirmed a match for \"$pet\" (match #$mid) "
              . "with no reunion recorded. If the finder is unresponsive, you can cancel this match "
              . "and your other pending matches will become available again."
            );
            $finder_msg = $conn->real_escape_string(
                "Final reminder: the owner of \"$pet\" is still waiting to hear from you (match #$mid). "
              . "If you no longer have the pet or cannot assist, please let them know so they can "
              . "explore other matches."
            );

            if (!$dry_run) {
                $conn->query("INSERT INTO notifications (user_id,type,message,link)
                    VALUES ($owner_id, 'match_reminder_7', '$owner_msg',
                            '../dashboard/match_contact.php?id=$mid')");
                $conn->query("INSERT INTO notifications (user_id,type,message,link)
                    VALUES ($finder_id,'match_reminder_7','$finder_msg',
                            '../public/view_found.php?id=$f_id')");
            }
            $sent += 2;
        } else {
            echo "  Match #$mid — day-7 reminder already sent, skipping\n";
        }
    }

    else {
        echo "  Match #$mid — $days_elapsed day(s) elapsed → no reminder due\n";
    }
}

echo "\nDone. " . ($dry_run ? "Would have sent" : "Sent") . " $sent notification(s).\n";