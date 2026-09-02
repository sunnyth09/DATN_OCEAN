class ApiResponseParser {
  /// Extract list data from generic API response
  static List<dynamic> parseList(dynamic decoded) {
    if (decoded is List) {
      return decoded;
    } else if (decoded is Map) {
      if (decoded['data'] is List) {
        return decoded['data'];
      } else if (decoded['data'] is Map && decoded['data']['data'] is List) {
        return decoded['data']['data'];
      } else if (decoded['orders'] is List) {
        return decoded['orders'];
      }
    }
    return [];
  }
}
