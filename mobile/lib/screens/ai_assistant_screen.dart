import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/ai.dart';
import '../services/ai_service.dart';

/// AI agent chat (وكلاء الذكاء الاصطناعي).
///
/// Lists the agents the signed-in user may talk to, then opens a chat that
/// streams the conversation against the backend. The agent can call read-only
/// tools (student counts, finance, attendance, ...) so the answers are grounded
/// in the school's real data.
class AiAssistantScreen extends StatefulWidget {
  const AiAssistantScreen({super.key, this.initialAgent});

  final String? initialAgent;

  @override
  State<AiAssistantScreen> createState() => _AiAssistantScreenState();
}

class _AiAssistantScreenState extends State<AiAssistantScreen> {
  AiService get _service => context.read<AiService>();

  late Future<AiOverview> _future;

  @override
  void initState() {
    super.initState();
    _future = _service.overview();
    if (widget.initialAgent != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _startConversation(widget.initialAgent!));
    }
  }

  void _reload() => setState(() => _future = _service.overview());

  Future<void> _startConversation(String agentKey) async {
    final conversation = await _service.createConversation(agentKey);
    if (!mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => AiChatScreen(conversation: conversation)),
    );
    _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('وكلاء الذكاء الاصطناعي')),
      body: FutureBuilder<AiOverview>(
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
                    OutlinedButton(onPressed: _reload, child: const Text('إعادة المحاولة')),
                  ],
                ),
              ),
            );
          }

          final overview = snapshot.data!;

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (!overview.available)
                Card(
                  color: Theme.of(context).colorScheme.errorContainer,
                  child: const Padding(
                    padding: EdgeInsets.all(16),
                    child: Row(
                      children: [
                        Icon(Icons.info_outline),
                        SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'ميزة الذكاء الاصطناعي غير مُهيّأة بعد. '
                            'يجب على مدير المنصة ضبط مفتاح المزوّد (AI_API_KEY) لتفعيل الوكلاء.',
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              Text('الوكلاء المتاحون', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              for (final agent in overview.agents)
                Card(
                  clipBehavior: Clip.antiAlias,
                  child: ListTile(
                    leading: const CircleAvatar(child: Icon(Icons.smart_toy_outlined)),
                    title: Text(agent.label),
                    subtitle: Text(agent.description),
                    trailing: const Icon(Icons.chevron_left),
                    onTap: () => _startConversation(agent.key),
                  ),
                ),
              const SizedBox(height: 24),
              if (overview.conversations.isNotEmpty) ...[
                Text('محادثاتي', style: Theme.of(context).textTheme.titleMedium),
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
                      onTap: () async {
                        await Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => AiChatScreen(conversation: conversation),
                          ),
                        );
                        _reload();
                      },
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

/// A single agent conversation.
class AiChatScreen extends StatefulWidget {
  const AiChatScreen({super.key, required this.conversation});

  final AiConversation conversation;

  @override
  State<AiChatScreen> createState() => _AiChatScreenState();
}

class _AiChatScreenState extends State<AiChatScreen> {
  AiService get _service => context.read<AiService>();

  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  final List<AiMessage> _messages = [];
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
      _messages.add(AiMessage(id: -1, role: 'user', content: text));
    });
    _controller.clear();
    _scrollToEnd();

    try {
      final reply = await _service.send(widget.conversation.id, text);
      if (!mounted) return;
      setState(() => _messages.add(reply.message));
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _sending = false);
      _scrollToEnd();
    }
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
      appBar: AppBar(title: const Text('محادثة الوكيل')),
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
                                child: CircularProgressIndicator(strokeWidth: 2)),
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
                        contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
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

  Widget _bubble(AiMessage message) {
    final isUser = message.isUser;
    final alignment = isUser ? Alignment.centerRight : Alignment.centerLeft;
    final color = isUser
        ? Theme.of(context).colorScheme.primaryContainer
        : Theme.of(context).colorScheme.surfaceContainerHighest;

    return Align(
      alignment: alignment,
      child: Container(
        constraints: BoxConstraints(
            maxWidth: MediaQuery.of(context).size.width * 0.8),
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
                    const Icon(Icons.smart_toy_outlined, size: 16),
                    const SizedBox(width: 4),
                    Text('الوكيل',
                        style: Theme.of(context).textTheme.labelSmall),
                  ],
                ),
              ),
            SelectableText(message.content),
          ],
        ),
      ),
    );
  }
}
