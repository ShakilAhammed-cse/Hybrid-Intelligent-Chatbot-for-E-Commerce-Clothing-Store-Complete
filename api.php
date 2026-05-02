<?php
header('Content-Type: application/json; charset=utf-8');

$message = trim($_POST['message'] ?? '');
if ($message === '') {
  echo json_encode(["reply" => "Type something 🙂"]);
  exit;
}

/*  API KEY HERE */
$GEMINI_API_KEY = "AIzaSyDg_OL_EOszfdPBLGiwCJVbSfGTFK_zYSM";

/* -------------------------
   1) SMALL UTIL FUNCTIONS
------------------------- */

function normalize_text($s) {
  $s = strtolower($s);
  $s = str_replace(["৳", "tk", "taka", "bdt"], " bdt ", $s);
  $s = preg_replace('/[^\p{L}\p{N}\s\.]/u', ' ', $s); // keep letters/numbers/spaces/dots
  $s = preg_replace('/\s+/', ' ', $s);
  return trim($s);
}

function tokenize($s) {
  $parts = preg_split('/\s+/', trim($s));
  $out = [];
  foreach ($parts as $p) {
    if ($p === '') continue;
    $out[] = $p;
  }
  return $out;
}

function extract_filters($msg) {
  $msg = normalize_text($msg);
  $tokens = tokenize($msg);

  $filters = [
    "size" => null,        // e.g. XL or 32
    "color" => null,       // black/white/blue/gray
    "max_price" => null,   // under/below
    "min_price" => null,   // above/over
    "exact_price" => null, // if user says exact
  ];

  // Size detection
  foreach ($tokens as $t) {
    $u = strtoupper($t);
    if (in_array($u, ["XS","S","M","L","XL","XXL"])) { $filters["size"] = $u; break; }
    if (preg_match('/^(28|30|32|34|36|38|40)$/', $t)) { $filters["size"] = $t; break; }
  }

  // Color detection (basic)
  $colors = ["black","white","blue","gray","grey","red","green","brown"];
  foreach ($colors as $c) {
    if (strpos($msg, $c) !== false) {
      $filters["color"] = ($c === "grey") ? "gray" : $c;
      break;
    }
  }

  // Price parsing:
  // handles: "under 1000", "below 1k", "less 900", "over 2000", "above 2k", "1000 bdt"
  $msg2 = str_replace(["k"], ["000"], $msg); // 1k -> 1000
  // find numbers
  preg_match_all('/\b(\d{2,6})\b/', $msg2, $m);
  $nums = array_map('intval', $m[1] ?? []);

  if (!empty($nums)) {
    $first = $nums[0];

    if (strpos($msg2, "under") !== false || strpos($msg2, "below") !== false || strpos($msg2, "less") !== false) {
      $filters["max_price"] = $first;
    } else if (strpos($msg2, "over") !== false || strpos($msg2, "above") !== false || strpos($msg2, "more") !== false) {
      $filters["min_price"] = $first;
    } else if (strpos($msg2, "bdt") !== false) {
      // If they mentioned BDT and only one number, treat as exact-ish budget
      $filters["exact_price"] = $first;
    }
  }

  return $filters;
}

function product_text($p) {
  $name  = strtolower($p["name"] ?? "");
  $title = strtolower($p["title"] ?? "");
  $sizes = strtolower(implode(" ", $p["sizes"] ?? []));
  $colors= strtolower(implode(" ", $p["colors"] ?? []));
  return trim("$name $title $sizes $colors");
}

function fuzzy_contains($haystack, $needle) {
  if ($needle === '') return false;
  if (strpos($haystack, $needle) !== false) return true;

  // fuzzy word match using levenshtein distance <= 2
  $hayWords = preg_split('/\s+/', $haystack);
  foreach ($hayWords as $w) {
    if ($w === '') continue;
    if (levenshtein($needle, $w) <= 2) return true;
  }
  return false;
}

function score_product($p, $tokens, $filters) {
  $text = product_text($p);
  $score = 0;

  // token scoring with fuzzy match
  foreach ($tokens as $t) {
    if ($t === '') continue;

    // ignore very small tokens
    if (strlen($t) <= 1) continue;

    if (fuzzy_contains($text, $t)) $score += 2;
  }

  // size boost
  if (!empty($filters["size"])) {
    $size = strtolower($filters["size"]);
    if (fuzzy_contains($text, $size)) $score += 4;
    else $score -= 1;
  }

  // color boost
  if (!empty($filters["color"])) {
    $color = strtolower($filters["color"]);
    if (fuzzy_contains($text, $color)) $score += 3;
    else $score -= 1;
  }

  // price logic
  $price = intval($p["price_bdt"] ?? 0);
  if (!empty($filters["max_price"]) && $price > $filters["max_price"]) $score -= 5;
  if (!empty($filters["min_price"]) && $price < $filters["min_price"]) $score -= 5;

  // if exact_price, prefer close prices
  if (!empty($filters["exact_price"])) {
    $diff = abs($price - $filters["exact_price"]);
    if ($diff <= 100) $score += 3;
    else if ($diff <= 300) $score += 1;
  }

  return $score;
}

/* -------------------------
   2) QUICK GREETING HANDLER
------------------------- */





/* -------------------------
   3) LOAD PRODUCTS
------------------------- */
$productsPath = __DIR__ . "/products.json";
$raw = file_exists($productsPath) ? file_get_contents($productsPath) : "[]";
$products = json_decode($raw, true);
if (!is_array($products)) $products = [];

/* -------------------------
   4) SMART MATCHING + FILTERS
------------------------- */
$norm = normalize_text($message);
$tokens = tokenize($norm);
$filters = extract_filters($message);

// score all products
$scored = [];
foreach ($products as $p) {
  $sc = score_product($p, $tokens, $filters);
  $scored[] = ["score" => $sc, "p" => $p];
}

usort($scored, function($a, $b) { return $b["score"] <=> $a["score"]; });

// take top matches with score threshold
$matched = [];
foreach ($scored as $row) {
  if ($row["score"] >= 2) $matched[] = $row["p"];
  if (count($matched) >= 5) break;
}

// If none matched, take best 3 as suggestions (even if low score)
$suggestions = [];
if (count($matched) === 0) {
  foreach ($scored as $row) {
    $suggestions[] = $row["p"];
    if (count($suggestions) >= 3) break;
  }
}

/* -------------------------
   5) CALL GEMINI TO WRITE A NICE REPLY
------------------------- */
$system = "You are Arroway Assistant, an AI for a clothing brand website.

Guidelines:
- Use PRODUCT DATA to answer product-related questions.
- If the question is not about products, respond normally as a helpful AI.
- Be friendly, concise, and professional.
- Understand typos and Bangla/English mixed language.
- Do not invent products.";


$payload = [
  "system_instruction" => ["parts" => [["text" => $system]]],
  "contents" => [[
    "role" => "user",
    "parts" => [[
      "text" =>
        "CUSTOMER MESSAGE:\n{$message}\n\n" .
        "DETECTED FILTERS (for you):\n" . json_encode($filters, JSON_PRETTY_PRINT) . "\n\n" .
        "PRODUCT DATA (best matches):\n" . json_encode($matched, JSON_PRETTY_PRINT) . "\n\n" .
        "SUGGESTIONS (if no match):\n" . json_encode($suggestions, JSON_PRETTY_PRINT)
    ]]
  ]]
];

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent";

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "Content-Type: application/json",
    "x-goog-api-key: " . $GEMINI_API_KEY
  ],
  CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
  echo json_encode(["reply" => "API error: $error"]);
  exit;
}

$data = json_decode($response, true);
$reply = $data["candidates"][0]["content"]["parts"][0]["text"] ?? "Sorry, I couldn’t answer.";

echo json_encode(["reply" => $reply]);
