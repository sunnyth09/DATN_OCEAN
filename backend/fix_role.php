<?php
$a = \App\Models\Admin::where('email', 'admin@ocean.com')->first();
$a->role = 'admin';
$a->save();
echo "Role fixed";
