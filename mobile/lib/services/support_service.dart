import '../core/network/api_client.dart';
import '../models/support.dart';

/// Customer-service assistant API (مساعد خدمة العملاء) plus the support desk.
class SupportService {
  SupportService(this._api);

  final ApiClient _api;

  Future<SupportOverview> overview() async {
    final data = await _api.get('support/assistant') as Map<String, dynamic>;
    return SupportOverview.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<SupportConversation> createConversation({String? title}) async {
    final data = await _api.post(
      'support/assistant/conversations',
      data: {'title': ?title},
    ) as Map<String, dynamic>;
    return SupportConversation.fromJson(
        (data['data'] as Map).cast<String, dynamic>());
  }

  Future<List<SupportMessage>> messages(int conversationId) async {
    final data = await _api.get('support/assistant/conversations/$conversationId')
        as Map<String, dynamic>;
    final payload = (data['data'] as Map)['messages'] as List? ?? const [];
    return payload
        .map((e) => SupportMessage.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<SupportReply> send(int conversationId, String message) async {
    final data = await _api.post(
      'support/assistant/conversations/$conversationId/messages',
      data: {'message': message},
    ) as Map<String, dynamic>;
    return SupportReply.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<void> deleteConversation(int conversationId) async {
    await _api.delete('support/assistant/conversations/$conversationId');
  }

  Future<List<SupportTicket>> tickets({String? status}) async {
    final data = await _api.get('support/tickets', query: {'status': ?status}) as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => SupportTicket.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<SupportTicket> openTicket({
    required String subject,
    required String body,
    String? category,
    int? conversationId,
  }) async {
    final data = await _api.post('support/tickets', data: {
      'subject': subject,
      'body': body,
      'category': ?category,
      'conversation_id': ?conversationId,
    }) as Map<String, dynamic>;
    return SupportTicket.fromJson((data['data'] as Map).cast<String, dynamic>());
  }
}
