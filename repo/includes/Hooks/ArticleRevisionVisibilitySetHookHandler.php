<?php

declare( strict_types = 1 );

namespace Wikibase\Repo\Hooks;

use MediaWiki\Hook\ArticleRevisionVisibilitySetHook;
use Title;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikibase\Repo\ChangeModification\DispatchChangeVisibilityNotificationJob;
use Wikibase\Repo\WikibaseRepo;

/**
 * Hook handler that propagates changes to the visibility of an article's revisions
 * to clients, through a job.
 *
 * This schedules a {@link DispatchChangeVisibilityNotificationJob DispatchChangeVisibilityNotification}
 * job, which will in turn schedule
 * {@link \Wikibase\Client\ChangeModification\ChangeVisibilityNotificationJob ChangeVisibilityNotification}
 * jobs on all client wikis (all as some wikis might no longer be subscribed)
 * which will handle this on the clients.
 * (Scheduling the client jobs directly in the hook handler may take too long for a web request.)
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 */
class ArticleRevisionVisibilitySetHookHandler implements ArticleRevisionVisibilitySetHook {

	/**
	 * @var EntityNamespaceLookup
	 */
	private $entityNamespaceLookup;

	/**
	 * @var callable
	 */
	private $jobQueueGroupFactory;

	public function __construct(
		EntityNamespaceLookup $entityNamespaceLookup,
		callable $jobQueueGroupFactory
	) {
		$this->entityNamespaceLookup = $entityNamespaceLookup;
		$this->jobQueueGroupFactory = $jobQueueGroupFactory;
	}

	public static function factory(): self {
		$wbRepo = WikibaseRepo::getDefaultInstance();

		return new self(
			$wbRepo->getLocalEntityNamespaceLookup(),
			'JobQueueGroup::singleton'
		);
	}

	/**
	 * @param Title $title
	 * @param int[] $ids
	 * @param int[][] $visibilityChangeMap
	 */
	public function onArticleRevisionVisibilitySet( $title, $ids, $visibilityChangeMap ): void {
		// Check if $title is in a wikibase namespace
		if ( !$this->entityNamespaceLookup->isEntityNamespace( $title->getNamespace() ) ) {
			return;
		}

		$job = new DispatchChangeVisibilityNotificationJob( $title, [
			'revisionIds' => array_map( 'intval', $ids ), // phpdoc says int[] but MediaWiki may call with a (int|string)[]
			'visibilityChangeMap' => $visibilityChangeMap,
		] );
		( $this->jobQueueGroupFactory )()->push( $job );
	}

}
