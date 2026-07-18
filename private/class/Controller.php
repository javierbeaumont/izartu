<?php
#  izartu: web bookmark manager based on tags
#
#  Copyright (C) 2011-2026 Javier Beaumont <javierbeaumont@users.noreply.github.com>
#
#  This file is part of izartu.
#
#  izartu is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as
#  published by the Free Software Foundation, either version 3 of the
#  License, or (at your option) any later version.
#
#  izartu is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with izartu. If not, see <https://www.gnu.org/licenses/>.

/**
 * Route handlers for the front controller (index.php). Each handler deals with
 * one route and returns a `[template, variables]` pair to render, or redirects
 * and exits directly.
 */
class Controller {

  /**
   * Home page: the public bookmark feed and tag cloud.
   *
   * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
   */
  public static function home(): array {
    return ['home', []];
  }

  /**
   * Login: show the form (GET) or process it (POST).
   *
   * On a valid POST (CSRF token plus credentials) the user is logged in and
   * redirected to the home page. Otherwise the form is shown, with an `error`
   * flag after a failed attempt.
   *
   * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
   */
  public static function login(): array {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (Auth::csrfCheck($_POST['csrf'] ?? null)
          && Auth::attempt($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        self::redirect(BASE.'/');
      }
      return ['login', ['error' => true]];
    }
    return ['login', []];
  }

  /**
   * Logout: destroy the session and redirect to the home page.
   *
   * @return never
   */
  public static function logout(): never {
    Auth::logout();
    self::redirect(BASE.'/');
  }

  /**
   * Not found: send a 404 status and render the not-found view.
   *
   * @return array{0: string, 1: array<string, mixed>} Template name and its variables.
   */
  public static function notFound(): array {
    http_response_code(404);
    return ['notfound', []];
  }

  /**
   * Send a redirect to the given path and stop the request.
   *
   * @param string $path Absolute URL path to redirect to.
   * @return never
   */
  public static function redirect(string $path): never {
    header('Location: '.$path);
    exit;
  }

}
