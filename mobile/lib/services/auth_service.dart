import 'dart:convert';

import '../core/network/api_client.dart';
import '../core/storage/token_store.dart';
import '../models/app_user.dart';

/// Result of a login attempt: either a full session or a 2FA challenge.
class LoginResult {
  LoginResult.session(this.user) : challengeToken = null;
  LoginResult.challenge(this.challengeToken) : user = null;

  final AppUser? user;
  final String? challengeToken;

  bool get requiresTwoFactor => challengeToken != null;
}

class AuthService {
  AuthService(this._api, this._tokens);

  final ApiClient _api;
  final TokenStore _tokens;

  Future<LoginResult> login(String email, String password,
      {String device = 'flutter'}) async {
    final data = await _api.post('auth/login', data: {
      'email': email,
      'password': password,
      'device_name': device,
    }) as Map<String, dynamic>;

    if (data['two_factor_required'] == true) {
      return LoginResult.challenge(data['challenge_token'] as String);
    }

    return await _persistSession(data);
  }

  Future<LoginResult> submitTwoFactor(String challengeToken, String code) async {
    final data = await _api.postWithToken(
      'auth/2fa/challenge',
      challengeToken,
      data: {'code': code},
    ) as Map<String, dynamic>;

    return _persistSession(data);
  }

  Future<AppUser?> me() async {
    final data = await _api.get('auth/me') as Map<String, dynamic>;
    final user = AppUser.fromJson(data['data'] as Map<String, dynamic>);
    await _tokens.saveUser(jsonEncode(user.toJson()));
    return user;
  }

  Future<AppUser?> restore() async {
    final raw = await _tokens.readUser();
    if (raw == null) return null;
    return AppUser.fromJson(jsonDecode(raw) as Map<String, dynamic>);
  }

  Future<void> logout() async {
    try {
      await _api.post('auth/logout');
    } catch (_) {
      // Best effort: clear locally even if the server call fails.
    }
    await _tokens.clear();
  }

  Future<LoginResult> _persistSession(Map<String, dynamic> data) async {
    final token = data['token'] as String;
    await _tokens.saveToken(token);
    final user = AppUser.fromJson(data['user'] as Map<String, dynamic>);
    await _tokens.saveUser(jsonEncode(user.toJson()));
    return LoginResult.session(user);
  }

}
