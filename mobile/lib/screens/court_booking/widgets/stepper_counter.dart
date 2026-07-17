import 'package:flutter/material.dart';

/// Bộ tăng/giảm số lượng, dùng trong dialog thêm dịch vụ.
class StepperCounter extends StatelessWidget {
  final int value;
  final ValueChanged<int> onChanged;

  const StepperCounter({super.key, required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        IconButton(
          onPressed: value > 1 ? () => onChanged(value - 1) : null,
          icon: const Icon(Icons.remove_circle_outline),
        ),
        SizedBox(
          width: 48,
          child: Center(
            child: Text(
              value.toString(),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
        ),
        IconButton(
          onPressed: () => onChanged(value + 1),
          icon: const Icon(Icons.add_circle_outline),
        ),
      ],
    );
  }
}
