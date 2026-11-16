<?php
/**
 * Plugin Name: CPT Sticky Posts
 * Description: カスタム投稿タイプに「先頭に固定」機能を追加し、REST APIでの取得に対応
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: cpt-sticky-posts
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPT_Sticky_Posts {
    
    /**
     * 対象のカスタム投稿タイプ（ここに対象のpost typeを追加）
     */
    private array $target_post_types = [];
    
    public function __construct() {
        add_action( 'init', [ $this, 'set_target_post_types' ], 20 );
        // Register on every request (not only REST) so the sanitize / auth callbacks
        // also apply to the classic editor, WP-CLI and other update_post_meta() calls.
        add_action( 'init', [ $this, 'register_sticky_meta' ], 21 );
        add_action( 'rest_api_init', [ $this, 'register_rest_query_params' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_sticky_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_sticky_meta' ] );
        add_filter( 'posts_orderby', [ $this, 'order_sticky_first' ], 10, 2 );
    }
    
    /**
     * 対象のカスタム投稿タイプを設定
     * フィルターで外部から設定可能
     */
    public function set_target_post_types(): void {
        // デフォルトでshow_in_rest = trueのカスタム投稿タイプを対象にする
        $post_types = get_post_types( [
            'public' => true,
            'show_in_rest' => true,
            '_builtin' => false, // 標準のpost/pageは除外
        ], 'names' );

        /**
         * 対象のカスタム投稿タイプをフィルター
         * @param array $post_types 対象の投稿タイプ配列
         */
        $this->target_post_types = apply_filters( 'cpt_sticky_posts_target_types', array_values( $post_types ) );

        // 各投稿タイプに対してカラムフックを登録
        foreach ( $this->target_post_types as $post_type ) {
            // manage_{$post_type}_posts_columns passes a single argument (the columns array).
            add_filter( "manage_{$post_type}_posts_columns", [ $this, 'add_sticky_column' ] );
            add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'render_sticky_column' ], 10, 2 );
        }
    }
    
    /**
     * REST APIにstickyメタフィールドを登録
     */
    public function register_sticky_meta(): void {
        foreach ( $this->target_post_types as $post_type ) {
            register_post_meta( $post_type, '_cpt_is_sticky', [
                'type'               => 'boolean',
                'description'        => __( 'この投稿を先頭に固定する', 'cpt-sticky-posts' ),
                'single'             => true,
                'default'            => false,
                'show_in_rest'       => true,
                'sanitize_callback'  => 'rest_sanitize_boolean',
                'auth_callback'      => function( $allowed, $meta_key, $post_id ) {
                    return current_user_can( 'edit_post', $post_id );
                },
            ] );
            
            // REST APIレスポンスにstickyフィールドを追加
            register_rest_field( $post_type, 'sticky', [
                'get_callback' => [ $this, 'get_sticky_field' ],
                'update_callback' => [ $this, 'update_sticky_field' ],
                'schema' => [
                    'description' => __( '先頭に固定表示', 'cpt-sticky-posts' ),
                    'type'        => 'boolean',
                    'context'     => [ 'view', 'edit' ],
                ],
            ] );
        }
    }
    
    /**
     * stickyフィールドの取得コールバック
     */
    public function get_sticky_field( array $post ): bool {
        return (bool) get_post_meta( $post['id'], '_cpt_is_sticky', true );
    }
    
    /**
     * stickyフィールドの更新コールバック
     */
    public function update_sticky_field( bool $value, WP_Post $post ): bool {
        // 自動保存時はスキップ
        if ( wp_is_post_autosave( $post->ID ) ) {
            return false;
        }

        return update_post_meta( $post->ID, '_cpt_is_sticky', $value );
    }
    
    /**
     * REST APIにクエリパラメータを登録
     */
    public function register_rest_query_params(): void {
        foreach ( $this->target_post_types as $post_type ) {
            // rest_{post_type}_query フィルターでクエリを拡張
            add_filter( "rest_{$post_type}_query", [ $this, 'filter_rest_query' ], 10, 2 );
            
            // クエリパラメータのスキーマを登録
            add_filter( "rest_{$post_type}_collection_params", [ $this, 'add_collection_params' ], 10, 1 );
        }
    }
    
    /**
     * REST APIクエリにstickyパラメータを追加
     */
    public function filter_rest_query( array $args, WP_REST_Request $request ): array {
        $sticky_param = $request->get_param( 'sticky' );
        $sticky_first = $request->get_param( 'sticky_first' );

        $meta_query = $args['meta_query'] ?? [];

        // sticky=true/false でフィルタリング
        if ( null !== $sticky_param ) {
            // Because a `sticky` collection param is registered, core's posts controller
            // rewrites post__in / post__not_in from the `sticky_posts` option (core's list
            // for regular posts). This plugin keeps the flag in post meta, so reset them
            // to what the client asked for with include / exclude.
            $args['post__in']     = (array) ( $request->get_param( 'include' ) ?? [] );
            $args['post__not_in'] = (array) ( $request->get_param( 'exclude' ) ?? [] );

            $sticky_value = filter_var( $sticky_param, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

            if ( null !== $sticky_value ) {
                if ( $sticky_value ) {
                    $meta_query[] = [
                        'key'     => '_cpt_is_sticky',
                        'value'   => '1',
                        'compare' => '=',
                    ];
                } else {
                    $meta_query[] = [
                        'relation' => 'OR',
                        [
                            'key'     => '_cpt_is_sticky',
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key'     => '_cpt_is_sticky',
                            'value'   => '',
                            'compare' => '=',
                        ],
                        [
                            'key'     => '_cpt_is_sticky',
                            'value'   => '0',
                            'compare' => '=',
                        ],
                    ];
                }
            }
        }

        // sticky_first=true でsticky投稿を先頭に
        if ( null !== $sticky_first && filter_var( $sticky_first, FILTER_VALIDATE_BOOLEAN ) ) {
            // A named meta_query clause cannot do this: WP_Query only orders by leaf
            // clauses, and filtering on the meta would drop posts without it. Flag the
            // query instead and prepend the sticky sort in order_sticky_first().
            // The requested orderby / order are left untouched.
            $args['cpt_sticky_first'] = true;
        }

        if ( ! empty( $meta_query ) ) {
            if ( ! isset( $meta_query['relation'] ) ) {
                $meta_query['relation'] = 'AND';
            }
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }
    
    /**
     * sticky_first=true のクエリでsticky投稿を先頭に並べる
     *
     * Posts without the meta are kept (no JOIN, no WHERE), and the original
     * ORDER BY stays as the secondary sort.
     */
    public function order_sticky_first( string $orderby, WP_Query $query ): string {
        if ( ! $query->get( 'cpt_sticky_first' ) ) {
            return $orderby;
        }

        global $wpdb;
        $sticky_order = $wpdb->prepare(
            "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS cpt_sticky_pm WHERE cpt_sticky_pm.post_id = {$wpdb->posts}.ID AND cpt_sticky_pm.meta_key = %s AND cpt_sticky_pm.meta_value = %s ) DESC",
            '_cpt_is_sticky',
            '1'
        );

        return '' === $orderby ? $sticky_order : $sticky_order . ', ' . $orderby;
    }
    
    /**
     * コレクションパラメータにsticky関連を追加
     */
    public function add_collection_params( array $params ): array {
        $params['sticky'] = [
            'description' => __( '先頭固定の投稿のみ取得(true)、または除外(false)', 'cpt-sticky-posts' ),
            'type'        => 'boolean',
            'default'     => null,
        ];
        
        $params['sticky_first'] = [
            'description' => __( '先頭固定の投稿を先頭に表示', 'cpt-sticky-posts' ),
            'type'        => 'boolean',
            'default'     => false,
        ];
        
        return $params;
    }
    
    /**
     * メタボックスを追加
     */
    public function add_sticky_meta_box(): void {
        foreach ( $this->target_post_types as $post_type ) {
            add_meta_box(
                'cpt_sticky_post',
                __( '先頭に固定', 'cpt-sticky-posts' ),
                [ $this, 'render_sticky_meta_box' ],
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * メタボックスの描画
     */
    public function render_sticky_meta_box( WP_Post $post ): void {
        $is_sticky = get_post_meta( $post->ID, '_cpt_is_sticky', true );
        wp_nonce_field( 'cpt_sticky_nonce', 'cpt_sticky_nonce_field' );
        ?>
        <label>
            <input type="checkbox" 
                   name="cpt_is_sticky" 
                   value="1" 
                   <?php checked( $is_sticky, true ); ?>>
            <?php esc_html_e( 'この投稿を先頭に固定表示する', 'cpt-sticky-posts' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'チェックするとREST APIで取得時に先頭に表示されます。', 'cpt-sticky-posts' ); ?>
        </p>
        <?php
    }
    
    /**
     * メタデータの保存
     */
    public function save_sticky_meta( int $post_id ): void {
        // Nonce検証
        if ( ! isset( $_POST['cpt_sticky_nonce_field'] ) ||
             ! wp_verify_nonce( $_POST['cpt_sticky_nonce_field'], 'cpt_sticky_nonce' ) ) {
            return;
        }
        
        // 自動保存時はスキップ
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // 権限チェック
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // 対象の投稿タイプかチェック
        if ( ! in_array( get_post_type( $post_id ), $this->target_post_types, true ) ) {
            return;
        }
        
        $is_sticky = isset( $_POST['cpt_is_sticky'] ) && $_POST['cpt_is_sticky'] === '1';
        update_post_meta( $post_id, '_cpt_is_sticky', $is_sticky );
    }
    
    /**
     * 投稿一覧にStickyカラムを追加
     */
    public function add_sticky_column( array $columns ): array {
        // Only hooked for target post types, so no post type check is needed here.
        $columns['cpt_sticky'] = __( '固定', 'cpt-sticky-posts' );
        return $columns;
    }
    
    /**
     * Stickyカラムの描画
     */
    public function render_sticky_column( string $column, int $post_id ): void {
        if ( $column === 'cpt_sticky' ) {
            $is_sticky = get_post_meta( $post_id, '_cpt_is_sticky', true );
            echo $is_sticky ? '<span class="dashicons dashicons-sticky" title="' . esc_attr__( '先頭に固定', 'cpt-sticky-posts' ) . '"></span>' : '—';
        }
    }
}

// プラグイン初期化
new CPT_Sticky_Posts();
