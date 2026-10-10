/// AI agents (وكلاء الذكاء الاصطناعي): agent descriptors, conversations and
/// messages.
library;

class AiAgent {
  AiAgent({required this.key, required this.label, this.description = ''});

  final String key;
  final String label;
  final String description;

  factory AiAgent.fromJson(Map<String, dynamic> json) => AiAgent(
        key: json['key']?.toString() ?? '',
        label: json['label']?.toString() ?? '',
        description: json['description']?.toString() ?? '',
      );
}

class AiConversation {
  AiConversation({
    required this.id,
    required this.agent,
    this.title,
    this.lastMessageAt,
  });

  final int id;
  final String agent;
  final String? title;
  final String? lastMessageAt;

  String get displayTitle =>
      (title != null && title!.isNotEmpty) ? title! : 'محادثة جديدة';

  factory AiConversation.fromJson(Map<String, dynamic> json) => AiConversation(
        id: int.parse(json['id'].toString()),
        agent: json['agent']?.toString() ?? '',
        title: json['title']?.toString(),
        lastMessageAt: json['last_message_at']?.toString(),
      );
}

class AiMessage {
  AiMessage({
    required this.id,
    required this.role,
    required this.content,
    this.model,
    this.createdAt,
  });

  final int id;
  final String role;
  final String content;
  final String? model;
  final String? createdAt;

  bool get isUser => role == 'user';

  factory AiMessage.fromJson(Map<String, dynamic> json) {
    final content = json['content'];
    return AiMessage(
      id: int.parse((json['id'] ?? 0).toString()),
      role: json['role']?.toString() ?? 'assistant',
      content: content is List
          ? content.map((e) => e?.toString() ?? '').join('\n')
          : content?.toString() ?? '',
      model: json['model']?.toString(),
      createdAt: json['created_at']?.toString(),
    );
  }
}

class AiOverview {
  AiOverview({
    required this.available,
    required this.agents,
    required this.conversations,
  });

  final bool available;
  final List<AiAgent> agents;
  final List<AiConversation> conversations;

  factory AiOverview.fromJson(Map<String, dynamic> json) => AiOverview(
        available: json['available'] == true,
        agents: (json['agents'] as List? ?? const [])
            .map((e) => AiAgent.fromJson((e as Map).cast<String, dynamic>()))
            .toList(),
        conversations: (json['conversations'] as List? ?? const [])
            .map((e) =>
                AiConversation.fromJson((e as Map).cast<String, dynamic>()))
            .toList(),
      );
}

class AiReply {
  AiReply({
    required this.message,
    this.toolsUsed = const [],
    this.notice,
    this.available = true,
  });

  final AiMessage message;
  final List<String> toolsUsed;
  final String? notice;
  final bool available;

  factory AiReply.fromJson(Map<String, dynamic> json) => AiReply(
        message: AiMessage.fromJson(
            (json['reply'] as Map).cast<String, dynamic>()),
        toolsUsed: (json['tools_used'] as List? ?? const [])
            .map((e) => e.toString())
            .toList(),
        notice: json['notice']?.toString(),
        available: json['available'] != false,
      );
}
