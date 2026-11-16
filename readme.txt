=== CPT Sticky Posts ===
Contributors: hideokamoto
Tags: sticky, custom post type, rest api
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

カスタム投稿タイプに「先頭に固定」機能を追加し、REST API での取得に対応します。

== Description ==

* カスタム投稿タイプの編集画面に「先頭に固定」チェックボックスを追加します。
* 投稿一覧画面に固定状態のカラムを追加します。
* REST API のレスポンスに `sticky` フィールド（boolean）を追加します。
* REST API のコレクションに `sticky`（固定投稿だけ / 固定以外だけ）と `sticky_first`（固定投稿を先頭に）のクエリパラメータを追加します。

対象は `public` かつ `show_in_rest` が有効なカスタム投稿タイプです。`cpt_sticky_posts_target_types` フィルターで変更できます。

== Installation ==

1. プラグインのフォルダ（例: `wp-cpt-rest-stiky`）を `/wp-content/plugins/` にアップロードします。
2. 管理画面の「プラグイン」から有効化します。

== Changelog ==

= Unreleased =
* Fix: 対象のカスタム投稿タイプの投稿一覧画面が致命的エラーで開けなかった問題を修正。
* Fix: REST API の `sticky=true` が常に空になり、`sticky=false` がコアの固定投稿の ID を除外していた問題を修正。
* Fix: REST API の `sticky_first=true` で固定投稿が先頭に並ばなかった問題を修正。
* Fix: `_cpt_is_sticky` メタを REST 以外（管理画面・WP-CLI など）で保存したときに真偽値へ変換されなかった問題を修正。

= 1.0.0 =
* 最初のリリース。
