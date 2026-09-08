<?php
/**
 * Demo collaborator seeder for Playground blueprints.
 *
 * Ships only with the Playground demo blueprints and is not part of the plugin.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seed demo collaborators into a post's awareness state.
 *
 * Creates users and writes their awareness entries through Presence API, which
 * is where sync-storage delegates awareness. The room and entries are also
 * stored in an option so the demo helper can re-stamp them past the server's
 * awareness timeout.
 *
 * @param int $count  Number of collaborators to seed.
 * @param int $offset Starting index for deterministic naming.
 * @return array<int> Created user IDs.
 */
function sync_storage_demo_seed( int $count, int $offset = 0 ): array {
	$names = array(
		'Alex Chen',
		'Jordan Lee',
		'Taylor Kim',
		'Morgan Davis',
		'Casey Brown',
		'Riley Martinez',
		'Jamie Wilson',
		'Avery Garcia',
		'Quinn Rodriguez',
		'Drew Anderson',
	);

	$user_ids = array();

	for ( $i = 0; $i < $count; $i++ ) {
		$index        = $offset + $i;
		$display_name = $names[ $index % count( $names ) ];
		$username     = 'demo-collaborator-' . ( $index + 1 );
		$email        = $username . '@example.com';

		$existing_user = get_user_by( 'login', $username );

		if ( $existing_user ) {
			$user_ids[] = $existing_user->ID;
		} else {
			$user_id = wp_create_user( $username, wp_generate_password(), $email );

			if ( is_wp_error( $user_id ) ) {
				continue;
			}

			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $display_name,
					'role'         => 'editor',
				)
			);

			$user_ids[] = $user_id;
		}
	}

	$posts = get_posts(
		array(
			'post_type'   => 'post',
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);

	if ( empty( $posts ) ) {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Collaborative Editing Demo',
				'post_status' => 'draft',
				'post_type'   => 'post',
			)
		);
	} else {
		$post_id = $posts[0]->ID;
	}

	$room    = 'postType/post:' . $post_id;
	$entries = array();

	foreach ( $user_ids as $index => $user_id ) {
		$user      = get_userdata( $user_id );
		$client_id = 1000 + $index;

		/*
		 * Nested under collaboratorInfo, not flat.
		 *
		 * The editor reads state.collaboratorInfo and runs it through
		 * isCollaboratorInfo(); a state that fails the check is dropped with
		 * no error. Presence stores whatever it is handed and the plugin
		 * passes it through untouched, so a flat shape survives every layer
		 * between here and the editor and then disappears silently.
		 */
		$state = array(
			'collaboratorInfo' => array(
				'id'          => $user_id,
				'name'        => $user->display_name,
				'slug'        => $user->user_nicename,
				'browserType' => 'Chrome',
				'enteredAt'   => time() * 1000,
				'avatar_urls' => array(
					'96' => get_avatar_url(
						$user_id,
						array( 'size' => 96 )
					),
				),
			),
		);

		$entries[] = array(
			'client_id'  => $client_id,
			'state'      => $state,
			'updated_at' => time(),
			'wp_user_id' => $user_id,
		);

		if ( function_exists( 'wp_set_presence' ) ) {
			wp_set_presence(
				$room,
				'sync-' . $client_id,
				$state,
				$user_id
			);
		}
	}

	update_option(
		'sync_storage_demo_entries',
		array(
			'room'    => $room,
			'post_id' => $post_id,
			'entries' => $entries,
		)
	);

	update_user_meta( 1, 'show_welcome_panel', 0 );

	return $user_ids;
}
