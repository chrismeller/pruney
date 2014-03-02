<?php

	class Pruney extends Plugin {

		public static $batch_size = 100;

		public function action_plugin_activation ( $file ) {

			CronTab::add_daily_cron( 'pruney_trim_posts', array( __CLASS__, 'trim_posts' ), _t( 'Check for posts to delete.' ) );

			// we don't want to accidentally re-activate it and delete posts without warning, so always delete the age
			Options::delete( 'pruney__age' );

			Session::notice( _t( 'Remember to configure the age for pruned posts!' ) );

		}

		public function action_plugin_deactivation ( $file ) {

			CronTab::delete_cronjob( 'pruney_trim_posts' );
			CronTab::delete_cronjob( 'pruney_trim_posts_once' );

			Options::delete( 'pruney__age' );

		}

		public function filter_plugin_config ( $actions ) {

			$actions['configure'] = _t( 'Configure' );
			$actions['trim_now'] = _t( 'Prune Now' );

			return $actions;

		}

		public function action_plugin_ui_configure ( ) {

			$ui = new FormUI( 'pruney' );

			$age = $ui->append( 'text', 'age', 'pruney__age', _t( 'Post age in days', 'pruney' ) );
			$age->add_validator( 'validate_required' );

			$ui->append( 'submit', 'save', _t( 'Save', 'pruney' ) );
			$ui->set_option( 'success_message', _t( 'Configuration saved', 'pruney' ) );
			$ui->on_success( array( $this, 'updated_config' ) );
			$ui->out();

		}

		public function action_plugin_ui ( $plugin_id, $action ) {

			if ( $action == 'trim_now' ) {
				self::trim_posts();
				Session::notice( _t( 'Posts trimmed. Check the Event Log for details.' ) );
			}

		}

		public function updated_config ( $ui ) {

			$ui->save();

			// add a single cron to get it to run "now"
			CronTab::add_single_cron( 'pruney_trim_posts_once', array( __CLASS__, 'trim_posts' ), 'now', _t( 'Check for posts to delete once only.' ) );

			Session::notice( _t( 'Posts will be trimmed in the background in batches of %d shortly.', array( self::$batch_size ) ) );

		}

		public static function trim_posts ( ) {

			$age = Options::get( 'pruney__age' );

			// since we're deleting stuff we don't want to assume anything as a default -- if the plugin hasn't been configured, do nothing
			if ( $age == null ) {
				// yes, this will match 0 as well -- 0 would be a stupid option
				return;
			}

			// first, figure out how many there are to delete
			$search = array(
				'content_type' => 'entry',
				'before' => HabariDateTime::date_create()->modify( '-' . $age . ' days' ),
				'status' => 'published',
				'limit' => self::$batch_size,
			);

			$trimmed = array();

			$posts = Posts::get( $search );
			foreach ( $posts as $post ) {

				$trimmed[] = $post->id;
				EventLog::log( _t( 'Automatically deleted post %s', array( $post->title ) ), 'notice' );

				$post->delete();

			}

			// may as well log all the IDs in the data section, that could be helpful
			EventLog::log( _t( 'Pruney ran and deleted %d posts', array( count( $trimmed ) ) ), 'notice', 'default', null, $trimmed );

			// make sure the cron knows we were successful
			return true;

		}

	}

?>