import 'package:flutter/foundation.dart';

import '../core/network/api_client.dart';
import '../models/app_user.dart';
import '../services/auth_service.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthProvider extends ChangeNotifier {
  AuthProvider(this._service);

  final AuthService _service;

  AuthStatus _status = AuthStatus.unknown;
  AppUser? _user;
  String? _challengeToken;
  String? _error;
  bool _busy = false;

  AuthStatus get status => _status;
  AppUser? get user => _user;
  bool get busy => _busy;
  String? get error => _error;
  bool get awaitingTwoFactor => _challengeToken != null;

  Future<void> bootstrap() async {
    final user = await _service.restore();
    _user = user;
    _status = user == null ? AuthStatus.unauthenticated : AuthStatus.authenticated;
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    _setBusy(true);
    try {
      final result = await _service.login(email, password);
      if (result.requiresTwoFactor) {
        _challengeToken = result.challengeToken;
        return false;
      }
      _user = result.user;
      _status = AuthStatus.authenticated;
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      return false;
    } finally {
      _setBusy(false);
    }
  }

  Future<bool> submitTwoFactor(String code) async {
    final token = _challengeToken;
    if (token == null) return false;

    _setBusy(true);
    try {
      final result = await _service.submitTwoFactor(token, code);
      _user = result.user;
      _challengeToken = null;
      _status = AuthStatus.authenticated;
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      return false;
    } finally {
      _setBusy(false);
    }
  }

  Future<void> logout() async {
    await _service.logout();
    _user = null;
    _challengeToken = null;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void _setBusy(bool value) {
    _busy = value;
    _error = null;
    notifyListeners();
  }
}
