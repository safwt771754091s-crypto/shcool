/// Customer-service assistant (مساعد خدمة العملاء): intents, conversations,
/// tickets and the assistant's reply metadata.
library;

class SupportIntent {
  SupportIntent({required this.key, required this.label});

  final String key;
  final String label;

  factory SupportIntent.fromJson(Map<String, dynamic> json) => SupportIntent(
        key: json['key']?.toString() ?? '',
        label: json['label']?.toString() ?? '',
      );
}

class SupportConversation {
  SupportConversation({
    required this.id,
    this.title,
    this.intent,
    this.lastMessageAt,
  });

  final int id;
  final String? title;
  final String? intent;
  final String? lastMessageAt;

  String get displayTitle =>
      (title != null && title!.isNotEmpty) ? title! : 'محادثة جديدة';

  factory SupportConversation.fromJson(Map<String, dynamic> json) =>
      SupportConversation(
        id: int.parse(json['id'].toString()),
        title: json['title']?.toString(),
        intent: json['intent']?.toString(),
        lastMessageAt: json['last_message_at']?.toString(),
      );
}

class SupportSource {
  SupportSource({
    required this.id,
    required this.question,
    this.category = '',
    this.score = 0,
  });

  final int id;
  final String question;
  final String category;
  final double score;

  factory SupportSource.fromJson(Map<String, dynamic> json) => SupportSource(
        id: int.parse((json['id'] ?? 0).toString()),
        question: json['question']?.toString() ?? '',
        category: json['category']?.toString() ?? '',
        score: (json['score'] as num?)?.toDouble() ?? 0,
      );
}

class SupportMessage {
  SupportMessage({
    required this.id,
    required this.role,
    required this.content,
    this.intent,
    this.confidence,
    this.sources = const [],
  });

  final int id;
  final String role;
  final String content;
  final String? intent;
  final double? confidence;
  final List<SupportSource> sources;

  bool get isUser => role == 'user';

  factory SupportMessage.fromJson(Map<String, dynamic> json) => SupportMessage(
        id: int.parse((json['id'] ?? 0).toString()),
        role: json['role']?.toString() ?? 'assistant',
        content: json['content']?.toString() ?? '',
        intent: json['intent']?.toString(),
        confidence: (json['confidence'] as num?)?.toDouble(),
        sources: (json['sources'] as List? ?? const [])
            .map((e) => SupportSource.fromJson((e as Map).cast<String, dynamic>()))
            .toList(),
      );
}

class SupportOverview {
  SupportOverview({
    required this.available,
    required this.intents,
    required this.conversations,
  });

  final bool available;
  final List<SupportIntent> intents;
  final List<SupportConversation> conversations;

  factory SupportOverview.fromJson(Map<String, dynamic> json) => SupportOverview(
        available: json['available'] == true,
        intents: (json['intents'] as List? ?? const [])
            .map((e) => SupportIntent.fromJson((e as Map).cast<String, dynamic>()))
            .toList(),
        conversations: (json['conversations'] as List? ?? const [])
            .map((e) =>
                SupportConversation.fromJson((e as Map).cast<String, dynamic>()))
            .toList(),
      );
}

class SupportReply {
  SupportReply({
    required this.message,
    required this.intent,
    required this.confidence,
    this.escalate = false,
    this.ticketReference,
    this.available = true,
    this.sources = const [],
  });

  final SupportMessage message;
  final String intent;
  final double confidence;
  final bool escalate;
  final String? ticketReference;
  final bool available;
  final List<SupportSource> sources;

  factory SupportReply.fromJson(Map<String, dynamic> json) => SupportReply(
        message: SupportMessage.fromJson(
            (json['reply'] as Map).cast<String, dynamic>()),
        intent: json['intent']?.toString() ?? 'general',
        confidence: (json['confidence'] as num?)?.toDouble() ?? 0,
        escalate: json['escalate'] == true,
        ticketReference:
            (json['ticket'] as Map?)?['reference']?.toString(),
        available: json['available'] != false,
        sources: (json['sources'] as List? ?? const [])
            .map((e) => SupportSource.fromJson((e as Map).cast<String, dynamic>()))
            .toList(),
      );
}

class SupportTicket {
  SupportTicket({
    required this.id,
    required this.reference,
    required this.subject,
    required this.status,
    this.category = 'general',
    this.priority = 'normal',
    this.lastMessageAt,
  });

  final int id;
  final String reference;
  final String subject;
  final String status;
  final String category;
  final String priority;
  final String? lastMessageAt;

  bool get isOpen => status == 'open' || status == 'pending';

  factory SupportTicket.fromJson(Map<String, dynamic> json) => SupportTicket(
        id: int.parse(json['id'].toString()),
        reference: json['reference']?.toString() ?? '',
        subject: json['subject']?.toString() ?? '',
        status: json['status']?.toString() ?? 'open',
        category: json['category']?.toString() ?? 'general',
        priority: json['priority']?.toString() ?? 'normal',
        lastMessageAt: json['last_message_at']?.toString(),
      );
}
