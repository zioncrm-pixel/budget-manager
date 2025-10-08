<?php
require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Carbon\Carbon;

echo "<h1>בדיקת תזרימים בודדים</h1>\n";

$user = User::first();
$year = Carbon::now()->year;
$month = Carbon::now()->month;

echo "<h2>פרטי חיפוש:</h2>\n";
echo "שנה: $year<br>\n";
echo "חודש: $month<br>\n";
echo "משתמש: {$user->name}<br>\n";

echo "<h2>תזרימים בודדים (ללא מקור תזרים):</h2>\n";
$individualTransactions = $user->transactions()
    ->with(['category'])
    ->whereYear('transaction_date', $year)
    ->whereMonth('transaction_date', $month)
    ->whereNull('cash_flow_source_id')
    ->orderBy('transaction_date', 'desc')
    ->get();

echo "מספר תזרימים בודדים: " . $individualTransactions->count() . "<br>\n";

foreach ($individualTransactions as $transaction) {
    echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>\n";
    echo "ID: {$transaction->id}<br>\n";
    echo "תיאור: {$transaction->description}<br>\n";
    echo "סכום: {$transaction->amount}<br>\n";
    echo "סוג: {$transaction->type}<br>\n";
    echo "תאריך: {$transaction->transaction_date}<br>\n";
    echo "קטגוריה: " . ($transaction->category->name ?? 'לא מוגדר') . "<br>\n";
    echo "מקור תזרים: " . ($transaction->cash_flow_source_id ?? 'null') . "<br>\n";
    echo "</div>\n";
}

echo "<h2>כל התזרימים בחודש:</h2>\n";
$allTransactions = $user->transactions()
    ->whereYear('transaction_date', $year)
    ->whereMonth('transaction_date', $month)
    ->get();

echo "מספר כל התזרימים: " . $allTransactions->count() . "<br>\n";

foreach ($allTransactions as $transaction) {
    echo "<div style='border: 1px solid #eee; margin: 5px; padding: 5px;'>\n";
    echo "ID: {$transaction->id} - {$transaction->description} - מקור: " . ($transaction->cash_flow_source_id ?? 'ללא') . "<br>\n";
    echo "</div>\n";
}


