import 'package:flutter_test/flutter_test.dart';
import 'package:school_app/core/network/api_client.dart';
import 'package:school_app/services/support_service.dart';

import 'support/fake_dio.dart';
import 'support/fake_token_store.dart';

void main() {
  test('SupportService.overview() parses intents and conversations', () async {
    final adapter = FakeAdapter((options) {
      expect(options.method, 'GET');
      expect(options.path, 'support/assistant');
      return jsonResponse({
        'data': {
          'available': true,
          'intents': [
            {'key': 'billing', 'label': 'الرسوم والفواتير'},
          ],
          'conversations': [
            {'id': 5, 'title': 'استفسار', 'intent': 'billing', 'last_message_at': '2026-01-01'},
          ],
        },
      });
    });

    final service = SupportService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final overview = await service.overview();

    expect(overview.available, isTrue);
    expect(overview.intents.single.key, 'billing');
    expect(overview.intents.single.label, 'الرسوم والفواتير');
    expect(overview.conversations.single.id, 5);
    expect(overview.conversations.single.displayTitle, 'استفسار');
  });

  test('SupportService.createConversation() posts and returns the conversation', () async {
    final adapter = FakeAdapter((options) {
      expect(options.method, 'POST');
      expect(options.path, 'support/assistant/conversations');
      return jsonResponse({
        'data': {'id': 8, 'title': null},
      }, statusCode: 201);
    });

    final service = SupportService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final conversation = await service.createConversation();

    expect(conversation.id, 8);
    expect(conversation.displayTitle, 'محادثة جديدة');
  });

  test('SupportService.send() parses intent, sources, escalate and ticket', () async {
    final adapter = FakeAdapter((options) {
      expect(options.method, 'POST');
      expect(options.path, 'support/assistant/conversations/1/messages');
      expect((options.data as Map)['message'], 'أريد التحدث مع موظف');
      return jsonResponse({
        'data': {
          'reply': {'id': 9, 'role': 'assistant', 'content': 'سأحوّلك إلى الدعم.'},
          'intent': 'handoff',
          'confidence': 0.35,
          'sources': [
            {'id': 4, 'question': 'كيف أسدد الرسوم؟', 'category': 'billing', 'score': 0.8},
          ],
          'escalate': true,
          'ticket': {'id': 1, 'reference': 'SD-ABC123', 'status': 'open'},
          'available': true,
        },
      });
    });

    final service = SupportService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final reply = await service.send(1, 'أريد التحدث مع موظف');

    expect(reply.intent, 'handoff');
    expect(reply.escalate, isTrue);
    expect(reply.ticketReference, 'SD-ABC123');
    expect(reply.sources.single.id, 4);
    expect(reply.message.content, 'سأحوّلك إلى الدعم.');
  });

  test('SupportService.tickets() parses the paginated payload', () async {
    final adapter = FakeAdapter((options) {
      expect(options.path, 'support/tickets');
      expect(options.queryParameters['status'], 'open');
      return jsonResponse({
        'data': [
          {
            'id': 1,
            'reference': 'SD-1',
            'subject': 'استفسار',
            'status': 'open',
            'category': 'billing',
            'priority': 'normal',
          },
        ],
        'total': 1,
      });
    });

    final service = SupportService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final tickets = await service.tickets(status: 'open');

    expect(tickets.single.reference, 'SD-1');
    expect(tickets.single.isOpen, isTrue);
  });

  test('SupportService.openTicket() posts the ticket and returns it', () async {
    final adapter = FakeAdapter((options) {
      expect(options.method, 'POST');
      expect(options.path, 'support/tickets');
      expect((options.data as Map)['subject'], 'مشكلة');
      return jsonResponse({
        'data': {
          'id': 2,
          'reference': 'SD-2',
          'subject': 'مشكلة',
          'status': 'open',
        },
      }, statusCode: 201);
    });

    final service = SupportService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final ticket = await service.openTicket(subject: 'مشكلة', body: 'تفاصيل');

    expect(ticket.reference, 'SD-2');
    expect(ticket.subject, 'مشكلة');
  });
}
