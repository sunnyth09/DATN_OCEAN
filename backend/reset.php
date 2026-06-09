<?php
$a = \App\Models\Admin::where('email', 'admin@ocean.com')->first();
$a->password = 'password123';
$a->save();
echo "Done";
