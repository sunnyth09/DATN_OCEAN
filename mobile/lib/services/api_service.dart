import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/product_model.dart';
import '../config/app_config.dart';

class ApiService {
  static const String baseUrl = AppConfig.kBaseUrl;

  static Future<List<Product>> fetchProducts() async {
    try {
      final response = await http.get(Uri.parse('$baseUrl/products'));

      if (response.statusCode == 200) {
        final Map<String, dynamic> jsonResponse = json.decode(utf8.decode(response.bodyBytes));
        
        // Trích xuất mảng data theo cấu trúc phổ biến của Laravel pagination/resource
        List<dynamic> data = [];
        if (jsonResponse['data'] != null) {
          data = jsonResponse['data'];
        } else if (jsonResponse.containsKey('products') && jsonResponse['products']['data'] != null) {
          data = jsonResponse['products']['data'];
        }

        return data.map((json) => Product.fromJson(json)).toList();
      } else {
        throw Exception('Failed to fetch data (Status ${response.statusCode})');
      }
    } catch (e) {
      throw Exception('API Connection Error: $e');
    }
  }
}
