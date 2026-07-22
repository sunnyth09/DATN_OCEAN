import os, re

path = 'lib/screens/chat_screen.dart'
with open(path, 'r', encoding='utf-8') as f:
    text = f.read()

# Add imports
imports = '''import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/chat_provider.dart';
import '../widgets/shimmer_loading.dart';
import '../config/app_theme.dart';'''
text = re.sub(r'import \'package:flutter/material\.dart\';\nimport \'../config/app_theme\.dart\';', imports, text)

# Remove mock data
vars_to_remove = [
    r'  final List<Map<String, dynamic>> _messages = \[[\s\S]*?\];\s*'
]
for v in vars_to_remove:
    text = re.sub(v, '', text, flags=re.MULTILINE)

# Add initState
init_state = '''
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ChatProvider>().fetchMessages();
    });
  }
'''
text = re.sub(r'class _ChatScreenState extends State<ChatScreen> \{', 'class _ChatScreenState extends State<ChatScreen> {' + init_state, text)

# Update _sendMessage
send_method_old = r'void _sendMessage\(\) \{[\s\S]*?\}\s*\}\);\s*\}\s*\n'
send_method_new = '''void _sendMessage() async {
    final text = _msgController.text.trim();
    if (text.isEmpty) return;

    final provider = context.read<ChatProvider>();
    _msgController.clear();
    await provider.sendMessage(text);
  }'''
text = re.sub(send_method_old, send_method_new + '\n', text, flags=re.MULTILINE)

# Update build method 
build_start = r'Widget build\(BuildContext context\) \{'
build_new = '''Widget build(BuildContext context) {
    final provider = context.watch<ChatProvider>();
    final _messages = provider.messages.reversed.toList(); // Reverse for bottom-up list
'''
text = re.sub(build_start, build_new, text)

# Add shimmer to ListView
listview_old = r'ListView\.builder\([\s\S]*?padding: const EdgeInsets\.all\(16\),[\s\S]*?reverse: true,[\s\S]*?itemCount: _messages\.length,[\s\S]*?itemBuilder: \(context, index\) \{[\s\S]*?final msg = _messages\[index\];[\s\S]*?final isMe = msg\[\'isMe\'\];[\s\S]*?return _buildMessageBubble\(msg\[\'text\'\], msg\[\'time\'\], isMe\);[\s\S]*?\},[\s\S]*?\)'

listview_new = '''provider.isLoading 
              ? Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      ShimmerLoading(width: 200, height: 50, borderRadius: 16),
                      const SizedBox(height: 12),
                      const Align(
                        alignment: Alignment.centerLeft,
                        child: ShimmerLoading(width: 200, height: 50, borderRadius: 16),
                      ),
                      const SizedBox(height: 12),
                      ShimmerLoading(width: 150, height: 50, borderRadius: 16),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  reverse: true,
                  itemCount: _messages.length,
                  itemBuilder: (context, index) {
                    final msg = _messages[index];
                    final isMe = msg['sender_type'] == 'user';
                    // Format time if it exists, otherwise just a fallback
                    final time = msg['created_at'] != null ? msg['created_at'].toString().substring(11, 16) : 'Vừa xong';
                    return _buildMessageBubble(msg['message'] ?? '', time, isMe);
                  },
                )'''

text = re.sub(listview_old, listview_new, text)

# Handle isSending UI state
send_button_old = r'IconButton\([\s\S]*?onPressed: _sendMessage,[\s\S]*?icon: const Icon\(Icons\.send, color: Colors\.white, size: 20\),[\s\S]*?\)'
send_button_new = '''provider.isSending 
                        ? const Padding(
                            padding: EdgeInsets.all(8.0),
                            child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)),
                          )
                        : IconButton(
                            onPressed: _sendMessage,
                            icon: const Icon(Icons.send, color: Colors.white, size: 20),
                          )'''
text = re.sub(send_button_old, send_button_new, text)

with open(path, 'w', encoding='utf-8') as f:
    f.write(text)

print("Fixed chat screen")
