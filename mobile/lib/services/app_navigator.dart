import 'package:flutter/material.dart';

/// Navigator key dùng chung để điều hướng từ ngoài widget tree
/// (xử lý click thông báo, auto-logout khi phiên hết hạn...).
final GlobalKey<NavigatorState> appNavigatorKey = GlobalKey<NavigatorState>();
