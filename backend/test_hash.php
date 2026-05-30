<?php
$a = \App\Models\Admin::where('email', 'admin@ocean.com')->first();
echo "Found: " . ($a ? "Yes" : "No") . "\n";
echo "Password: " . $a->password . "\n";
echo "Match: " . (Hash::check('password123', $a->password) ? "Yes" : "No") . "\n";
$a->password = Hash::make('password123'); // try manually hashing since maybe the cast wasn't working?
$a->save();
echo "Hash updated\n";
