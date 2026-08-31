import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/models/cart_model.dart';

void main() {
  group('CartVariant', () {
    test('fromJson parses all fields correctly', () {
      final json = {
        'variant_id': 42,
        'price': 199000,
        'original_price': 299000,
        'is_on_sale': true,
        'color': 'Đỏ',
        'size': 'XL',
        'image_url': '/images/variant.jpg',
        'stock_quantity': 15,
      };

      final variant = CartVariant.fromJson(json);

      expect(variant.variantId, 42);
      expect(variant.price, 199000);
      expect(variant.originalPrice, 299000);
      expect(variant.isOnSale, true);
      expect(variant.color, 'Đỏ');
      expect(variant.size, 'XL');
      expect(variant.imageUrl, '/images/variant.jpg');
      expect(variant.stock, 15);
    });

    test('fromJson handles string numeric values', () {
      final json = {
        'id': '7',
        'price': '149000.00',
        'stock': '30',
      };

      final variant = CartVariant.fromJson(json);

      expect(variant.variantId, 7);
      expect(variant.price, 149000);
      expect(variant.stock, 30);
    });

    test('fromJson handles null values gracefully', () {
      final variant = CartVariant.fromJson({});

      expect(variant.variantId, isNull);
      expect(variant.price, 0);
      expect(variant.originalPrice, 0);
      expect(variant.isOnSale, false);
      expect(variant.color, isNull);
      expect(variant.size, isNull);
    });

    test('label returns formatted variant description', () {
      expect(
        CartVariant.fromJson({'color': 'Đen', 'size': 'M'}).label,
        'Đen | Size M',
      );
      expect(
        CartVariant.fromJson({'color': 'Xanh'}).label,
        'Xanh',
      );
      expect(
        CartVariant.fromJson({'size': 'L'}).label,
        'Size L',
      );
      expect(
        CartVariant.fromJson({}).label,
        '',
      );
    });

    test('toJson round-trips correctly', () {
      final json = {
        'variant_id': 5,
        'price': 100000,
        'original_price': 200000,
        'is_on_sale': true,
        'color': 'Trắng',
        'size': 'S',
        'image_url': '/img.jpg',
        'stock_quantity': 10,
      };

      final variant = CartVariant.fromJson(json);
      final output = variant.toJson();

      expect(output['variant_id'], 5);
      expect(output['price'], 100000);
      expect(output['color'], 'Trắng');
    });
  });

  group('CartItem', () {
    test('fromJson parses product + variant', () {
      final json = {
        'cart_item_id': 101,
        'quantity': 3,
        'product': {
          'name': 'Vợt cầu lông Yonex',
          'stock_quantity': 50,
        },
        'variant': {
          'variant_id': 42,
          'price': 850000,
          'color': 'Đỏ',
          'size': 'G5',
        },
      };

      final item = CartItem.fromJson(json);

      expect(item.cartItemId, 101);
      expect(item.quantity, 3);
      expect(item.productName, 'Vợt cầu lông Yonex');
      expect(item.variant, isNotNull);
      expect(item.variant!.variantId, 42);
      expect(item.variant!.price, 850000);
      expect(item.productStock, 50);
    });

    test('price returns variant price', () {
      final item = CartItem.fromJson({
        'cart_item_id': 1,
        'quantity': 2,
        'product': {'name': 'Test'},
        'variant': {'price': 500000},
      });

      expect(item.price, 500000);
    });

    test('price returns 0 when no variant', () {
      final item = CartItem.fromJson({
        'cart_item_id': 1,
        'quantity': 1,
        'product': {'name': 'Test'},
      });

      expect(item.price, 0);
    });

    test('lineTotal = price × quantity', () {
      final item = CartItem.fromJson({
        'cart_item_id': 1,
        'quantity': 3,
        'product': {'name': 'Test'},
        'variant': {'price': 100000},
      });

      expect(item.lineTotal, 300000);
    });

    test('maxQuantity uses variant stock first, then product stock', () {
      final withVariantStock = CartItem.fromJson({
        'cart_item_id': 1,
        'quantity': 1,
        'product': {'name': 'Test', 'stock': 100},
        'variant': {'price': 1000, 'stock': 5},
      });
      expect(withVariantStock.maxQuantity, 5);

      final withProductStock = CartItem.fromJson({
        'cart_item_id': 1,
        'quantity': 1,
        'product': {'name': 'Test', 'stock_quantity': 20},
      });
      expect(withProductStock.maxQuantity, 20);

      final noStock = CartItem.fromJson({
        'cart_item_id': 1,
        'quantity': 1,
        'product': {'name': 'Test'},
      });
      expect(noStock.maxQuantity, 99);
    });

    test('copyWith creates new item with updated quantity', () {
      final item = CartItem.fromJson({
        'cart_item_id': 1,
        'quantity': 2,
        'product': {'name': 'Original'},
        'variant': {'price': 50000},
      });

      final updated = item.copyWith(quantity: 5);

      expect(updated.quantity, 5);
      expect(updated.cartItemId, 1);
      expect(updated.productName, 'Original');
      expect(updated.price, 50000);
    });
  });

  group('Cart', () {
    test('fromJson parses items array', () {
      final json = {
        'items': [
          {
            'cart_item_id': 1,
            'quantity': 2,
            'product': {'name': 'SP1'},
            'variant': {'price': 100000},
          },
          {
            'cart_item_id': 2,
            'quantity': 1,
            'product': {'name': 'SP2'},
            'variant': {'price': 200000},
          },
        ],
        'total_price': 400000,
      };

      final cart = Cart.fromJson(json);

      expect(cart.items.length, 2);
      expect(cart.items[0].productName, 'SP1');
      expect(cart.items[1].productName, 'SP2');
      expect(cart.serverTotalPrice, 400000);
    });

    test('empty cart constants', () {
      expect(Cart.empty.isEmpty, true);
      expect(Cart.empty.totalQuantity, 0);
      expect(Cart.empty.totalPrice, 0);
    });

    test('totalQuantity sums all item quantities', () {
      final cart = Cart.fromJson({
        'items': [
          {'cart_item_id': 1, 'quantity': 2, 'product': {'name': 'A'}, 'variant': {'price': 100}},
          {'cart_item_id': 2, 'quantity': 3, 'product': {'name': 'B'}, 'variant': {'price': 200}},
        ],
      });

      expect(cart.totalQuantity, 5);
    });

    test('totalPrice uses serverTotalPrice when available', () {
      final cart = Cart.fromJson({
        'items': [
          {'cart_item_id': 1, 'quantity': 1, 'product': {'name': 'A'}, 'variant': {'price': 100000}},
        ],
        'total_price': 90000, // Discounted
      });

      expect(cart.totalPrice, 90000);
    });

    test('totalPrice calculates from items when no server price', () {
      final cart = Cart.fromJson({
        'items': [
          {'cart_item_id': 1, 'quantity': 2, 'product': {'name': 'A'}, 'variant': {'price': 100000}},
          {'cart_item_id': 2, 'quantity': 1, 'product': {'name': 'B'}, 'variant': {'price': 50000}},
        ],
      });

      expect(cart.totalPrice, 250000); // 2*100000 + 1*50000
    });

    test('fromJson handles empty/null items', () {
      expect(Cart.fromJson({}).isEmpty, true);
      expect(Cart.fromJson({'items': null}).isEmpty, true);
      expect(Cart.fromJson({'items': []}).isEmpty, true);
    });
  });
}
