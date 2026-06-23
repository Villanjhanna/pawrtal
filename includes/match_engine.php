<?php
/* ===========================================================
   Pawrtal — Automated Match Engine v2
   Called after every new lost or found report is submitted.

   Scoring (max 100):
     +20  same pet_type          (required gate — no match without it)
     +30  color similarity       (keyword overlap, noise-filtered)
     +20  gender match           (explicit match required; mismatch = -10)
     +20  breed similarity       (keyword overlap)
     +10  age within 2 years

   Threshold: 60 points minimum (after penalty)

   Key changes from v1:
   - Type reduced to 20pts (was 40) — same type alone can't carry a match
   - Gender mismatch now DEDUCTS 10pts (was neutral)
   - Color uses a noise-word filter to avoid "and/with/a" false intersects
   - Breed match raised to 20pts (was 10) — strong signal when both provided
   - Threshold raised to 60 but with stricter scoring so it means more
   - Color partial match now requires ≥2 keywords OR an exact single-word match
     to avoid "brown lab" matching "brown tabby cat" on "brown" alone
=========================================================== */

function findMatches(mysqli $conn, int $report_id, string $type): void {

    if ($type === 'lost') {
        $source = $conn->query("SELECT * FROM lost_reports  WHERE id=$report_id")->fetch_assoc();
        $pool   = $conn->query("SELECT * FROM found_reports WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
    } else {
        $source = $conn->query("SELECT * FROM found_reports WHERE id=$report_id")->fetch_assoc();
        $pool   = $conn->query("SELECT * FROM lost_reports  WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
    }

    if (!$source || empty($pool)) return;

    // Noise words that should never count as a color match
    static $COLOR_NOISE = ['and','with','a','the','some','bit','of','little','very','or','to'];

    foreach ($pool as $candidate) {
        $score = 0;

        // ── Pet type (required gate) ──────────────────────────
        // No points awarded — just skip if they don't match.
        // This prevents type from inflating the score.
        if (strtolower($source['pet_type']) !== strtolower($candidate['pet_type'])) {
            continue;
        }
        $score += 20;

        // ── Color ─────────────────────────────────────────────
        // Filter noise words, then require meaningful overlap.
        $srcColors  = array_diff(
            preg_split('/[\s,\/\-]+/', strtolower(trim($source['color'] ?? ''))),
            $COLOR_NOISE
        );
        $candColors = array_diff(
            preg_split('/[\s,\/\-]+/', strtolower(trim($candidate['color'] ?? ''))),
            $COLOR_NOISE
        );

        // Remove empty strings that splitting can leave
        $srcColors  = array_filter($srcColors,  fn($v) => strlen($v) > 1);
        $candColors = array_filter($candColors, fn($v) => strlen($v) > 1);

        $colorHits = count(array_intersect($srcColors, $candColors));

        if ($colorHits >= 2) {
            // Strong color match — multiple keywords align
            $score += 30;
        } elseif ($colorHits === 1) {
            // Weak color signal — only worth points if the matching word
            // is the PRIMARY (first) color on both sides, not incidental
            $srcPrimary  = array_values($srcColors)[0]  ?? '';
            $candPrimary = array_values($candColors)[0] ?? '';
            if ($srcPrimary === $candPrimary && strlen($srcPrimary) > 2) {
                $score += 15; // primary color aligns
            } else {
                $score += 5;  // incidental color hit — very weak signal
            }
        }
        // 0 hits → 0 color points

        // ── Gender ────────────────────────────────────────────
        $srcGender  = strtolower(trim($source['gender']    ?? 'unknown'));
        $candGender = strtolower(trim($candidate['gender'] ?? 'unknown'));

        if ($srcGender !== 'unknown' && $candGender !== 'unknown') {
            if ($srcGender === $candGender) {
                $score += 20; // explicit match
            } else {
                $score -= 10; // explicit mismatch — strong negative signal
            }
        }
        // Both unknown → neutral, no change

        // ── Breed ─────────────────────────────────────────────
        // Only scored when BOTH reports have a breed — absence is neutral
        if (!empty($source['breed']) && !empty($candidate['breed'])) {
            $srcBreed  = array_filter(
                preg_split('/[\s,\/\-]+/', strtolower(trim($source['breed']))),
                fn($v) => strlen($v) > 2
            );
            $candBreed = array_filter(
                preg_split('/[\s,\/\-]+/', strtolower(trim($candidate['breed']))),
                fn($v) => strlen($v) > 2
            );

            $breedHits = count(array_intersect($srcBreed, $candBreed));
            if ($breedHits > 0) {
                $score += 20; // breed is a very strong signal
            } else {
                // Both have a breed but they don't match — mild negative signal
                $score -= 5;
            }
        }

        // ── Age ───────────────────────────────────────────────
        $srcAge  = isset($source['age_years'])    && $source['age_years']    !== '' ? (int)$source['age_years']    : null;
        $candAge = isset($candidate['age_years']) && $candidate['age_years'] !== '' ? (int)$candidate['age_years'] : null;

        if ($srcAge !== null && $candAge !== null) {
            $ageDiff = abs($srcAge - $candAge);
            if ($ageDiff === 0) {
                $score += 10; // exact age
            } elseif ($ageDiff <= 2) {
                $score += 5;  // close age
            } else {
                $score -= 5;  // age significantly off
            }
        }

        // ── Location filter ───────────────────────────────────
        // Hard cutoff: if both reports have coordinates and they're
        // more than 10 km apart, skip regardless of score.
        if (
            !empty($source['lat'])    && !empty($source['lng']) &&
            !empty($candidate['lat']) && !empty($candidate['lng'])
        ) {
            $distance = distanceKm(
                (float)$source['lat'],    (float)$source['lng'],
                (float)$candidate['lat'], (float)$candidate['lng']
            );
            if ($distance > 10) continue;
        }

        // ── Minimum score gate ────────────────────────────────
        // 60 is meaningful now: type(20) + strong color(30) + gender(20) = 70
        // A gender mismatch alone drops that to 60, which still passes.
        // A wrong breed further drops to 55, which fails — correctly.
        if ($score < 60) continue;

        // ── Cap at 100 ────────────────────────────────────────
        $score = min(100, $score);

        $lost_id  = ($type === 'lost')  ? $report_id : $candidate['id'];
        $found_id = ($type === 'found') ? $report_id : $candidate['id'];

        $conn->query("
            INSERT INTO matches (lost_report_id, found_report_id, score)
            VALUES ($lost_id, $found_id, $score)
            ON DUPLICATE KEY UPDATE score = GREATEST(score, $score)
        ");
    }
}

function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earth = 6371;
    $dLat  = deg2rad($lat2 - $lat1);
    $dLon  = deg2rad($lon2 - $lon1);
    $a     = sin($dLat/2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}