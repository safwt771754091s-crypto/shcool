import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/support.dart';
import '../services/support_service.dart';

/// Customer-service assistant (مساعد خدمة العملاء).
///
/// A KB-grounded chat that answers common questions, redacts personal data
/// before anything leaves the device, and escalates to a human ticket when the
/// assistant is unsure or the user asks for one.
class SupportAssistantScreen extends StatefulWidget {
  const SupportAssistantScreen({super.key});

  @override
  State<SupportAssistantScreen> createState() => _SupportAssistantScreenState();
}

class _SupportAssistantScreenState extends State<SupportAssistantScreen> {
  SupportService get _service => context.read<SupportService>();

  late Future<SupportOverview> _future;

  @override
  void initState() {
    super.initState();
    _future = _service.overview();
  }

  void _reload() => setState(() => _future = _service.overview());

  Future<void> _open(SupportConversation conversation) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SupportChatScreen(conversation: conversation),
      ),
    );
    _reload();
  }

  Future<void> _start() async {
    final conversation = await _service.createConversation();
    if (!mounted) return;
    await _open(conversation);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('مساعد خدمة العملاء')),
      body: FutureBuilder<SupportOverview>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.cloud_off_outlined, size: 48),
                    const SizedBox(height: 12),
                    Text('${snapshot.error}', textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    OutlinedButton(
                        onPressed: _reload, child: const Text('إعادة المحاولة')),
                  ],
                ),
              ),
            );
          }

          final overview = snapshot.data!;

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                clipBehavior: Clip.antiAlias,
                color: Theme.of(context).colorScheme.primaryContainer,
                child: ListTile(
                  leading: const Icon(Icons.support_agent_outlined),
                  title: const Text('ابدأ محادثة جديدة'),
                  subtitle: const Text('اسأل عن الرسوم، الحضور، النتائج، أو أي مشكلة تقنية'),
                  trailing: const Icon(Icons.chevron_left),
                  onTap: _start,
                ),
              ),
              const SizedBox(height: 16),
              Text('يمكنني المساعدة في',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final intent in overview.intents)
                    Chip(label: Text(intent.label)),
                ],
              ),
              const SizedBox(height: 24),
              if (overview.conversations.isNotEmpty) ...[
                Text('محادثاتي',
                    style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                for (final conversation in overview.conversations)
                  Card(
                    clipBehavior: Clip.antiAlias,
                    child: ListTile(
                      leading: const Icon(Icons.forum_outlined),
                      title: Text(conversation.displayTitle,
                          maxLines: 1, overflow: TextOverflow.ellipsis),
                      subtitle: Text(conversation.lastMessageAt ?? ''),
                      trailing: IconButton(
                        tooltip: 'حذف',
                        icon: const Icon(Icons.delete_outline),
                        onPressed: () async {
                          await _service.deleteConversation(conversation.id);
                          _reload();
                        },
                      ),
                      onTap: () => _open(conversation),
                    ),
                  ),
              ],
            ],
          );
        },
      ),
    );
  }
}

/// A single support conversation.
class SupportChatScreen extends StatefulWidget {
  const SupportChatScreen({super.key, required this.conversation});

  final SupportConversation conversation;

  @override
  State<SupportChatScreen> createState() => _SupportChatScreenState();
}

class _SupportChatScreenState extends State<SupportChatScreen> {
  SupportService get _service => context.read<SupportService>();

  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  final List<SupportMessage> _messages = [];
  bool _loading = true;
  bool _sending = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final messages = await _service.messages(widget.conversation.id);
      if (!mounted) return;
      setState(() {
        _messages
          ..clear()
          ..addAll(messages);
        _loading = false;
      });
      _scrollToEnd();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = '$e';
      });
    }
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    if (text.isEmpty || _sending) return;

    setState(() {
      _sending = true;
      _error = null;
      _messages.add(SupportMessage(id: -1, role: 'user', content: text));
    });
    _controller.clear();
    _scrollToEnd();

    try {
      final reply = await _service.send(widget.conversation.id, text);
      if (!mounted) return;
      setState(() {
        _messages.add(reply.message);
        if (reply.escalate && reply.ticketReference != null) {
          _showEscalation(reply.ticketReference!);
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _sending = false);
      _scrollToEnd();
    }
  }

  void _showEscalation(String reference) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('تم تحويل طلبك إلى موظف الدعم. رقم التذكرة: $reference'),
        action: SnackBarAction(label: 'حسناً', onPressed: () {}),
      ),
    );
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('مساعد خدمة العملاء')),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(16),
                    itemCount: _messages.length + (_sending ? 1 : 0),
                    itemBuilder: (context, index) {
                      if (index >= _messages.length) {
                        return const Align(
                          alignment: Alignment.centerRight,
                          child: Padding(
                            padding: EdgeInsets.symmetric(vertical: 8),
                            child: SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2)),
                          ),
                        );
                      }
                      return _bubble(_messages[index]);
                    },
                  ),
          ),
          if (_error != null)
            Material(
              color: Theme.of(context).colorScheme.errorContainer,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Text(_error!, textAlign: TextAlign.center),
              ),
            ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      minLines: 1,
                      maxLines: 4,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _send(),
                      decoration: const InputDecoration(
                        hintText: 'اكتب سؤالك…',
                        border: OutlineInputBorder(),
                        contentPadding:
                            EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _sending ? null : _send,
                    icon: const Icon(Icons.send),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _bubble(SupportMessage message) {
    final isUser = message.isUser;
    final alignment = isUser ? Alignment.centerRight : Alignment.centerLeft;
    final color = isUser
        ? Theme.of(context).colorScheme.primaryContainer
        : Theme.of(context).colorScheme.surfaceContainerHighest;

    return Align(
      alignment: alignment,
      child: Container(
        constraints:
            BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.8),
        margin: const EdgeInsets.symmetric(vertical: 6),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (!isUser)
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.support_agent_outlined, size: 16),
                    const SizedBox(width: 4),
                    Text('المساعد',
                        style: Theme.of(context).textTheme.labelSmall),
                  ],
                ),
              ),
            SelectableText(message.content),
            if (!isUser && message.sources.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  'المصادر: ${message.sources.map((s) => '#${s.id}').join(' ')}',
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              ),
          ],
        ),
      ),
    );
  }
}
