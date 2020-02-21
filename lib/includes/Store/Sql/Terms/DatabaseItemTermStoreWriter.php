<?php

namespace Wikibase\Lib\Store\Sql\Terms;

use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use Wikibase\DataAccess\EntitySource;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Term\ItemTermStoreWriter;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\StringNormalizer;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * ItemTermStoreWriter implementation for the 2019 SQL based secondary item term storage
 *
 * @see @ref md_docs_storage_terms
 * @license GPL-2.0-or-later
 */
class DatabaseItemTermStoreWriter implements ItemTermStoreWriter {

	use FingerprintableEntityTermStoreTrait;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var TermInLangIdsAcquirer */
	private $termInLangIdsAcquirer;

	/** @var TermStoreCleaner */
	private $termInLangIdsCleaner;

	/** @var StringNormalizer */
	private $stringNormalizer;

	/** @var IDatabase|null */
	private $dbw = null;

	/** @var EntitySource */
	private $entitySource;

	public function __construct(
		ILoadBalancer $loadBalancer,
		TermInLangIdsAcquirer $termInLangIdsAcquirer,
		TermStoreCleaner $termInLangIdsCleaner,
		StringNormalizer $stringNormalizer,
		EntitySource $entitySource
	) {
		$this->loadBalancer = $loadBalancer;
		$this->termInLangIdsAcquirer = $termInLangIdsAcquirer;
		$this->termInLangIdsCleaner = $termInLangIdsCleaner;
		$this->stringNormalizer = $stringNormalizer;
		$this->entitySource = $entitySource;
	}

	private function getDbw(): IDatabase {
		if ( $this->dbw === null ) {
			$this->dbw = $this->loadBalancer->getConnection( ILoadBalancer::DB_MASTER );
		}
		return $this->dbw;
	}

	public function storeTerms( ItemId $itemId, Fingerprint $fingerprint ) {
		MediaWikiServices::getInstance()->getStatsdDataFactory()->increment(
			'wikibase.repo.term_store.ItemTermStore_storeTerms'
		);
		$this->assertCanWriteItemTerms();

		$termInLangIdsToClean = $this->acquireAndInsertTerms( $itemId, $fingerprint );
		if ( $termInLangIdsToClean !== [] ) {
			$this->cleanTermsIfUnused( $termInLangIdsToClean );
		}
	}

	/**
	 * Acquire term in lang IDs for the given Fingerprint,
	 * store them in wbt_item_terms for the given item ID,
	 * and return term in lang IDs that are no longer referenced
	 * and might now need to be cleaned up.
	 *
	 * @param ItemId $itemId
	 * @param Fingerprint $fingerprint
	 *
	 * @return int[] wbit_term_in_lang_ids to that are no longer used by $itemId
	 * The returned term in lang IDs might still be used in wbt_item_terms rows
	 * for other item IDs or elsewhere, and this should be checked just before cleanup.
	 * However, that may happen in a different transaction than this call.
	 */
	private function acquireAndInsertTerms( ItemId $itemId, Fingerprint $fingerprint ): array {
		// Find term entries that already exist for the item
		$oldTermInLangIds = $this->getDbw()->selectFieldValues(
			'wbt_item_terms',
			'wbit_term_in_lang_id',
			[ 'wbit_item_id' => $itemId->getNumericId() ],
			__METHOD__,
			[ 'FOR UPDATE' ]
		);

		$termsArray = $this->termsArrayFromFingerprint( $fingerprint, $this->stringNormalizer );
		$termInLangIdsToClean = [];
		$fname = __METHOD__;

		// Acquire all of the Term in lang Ids needed for the wbt_item_terms table
		$this->termInLangIdsAcquirer->acquireTermInLangIds(
			$termsArray,
			function ( array $newTermInLangIds ) use ( $itemId, $oldTermInLangIds, &$termInLangIdsToClean, $fname ) {
				$termInLangIdsToInsert = array_diff( $newTermInLangIds, $oldTermInLangIds );
				$termInLangIdsToClean = array_diff( $oldTermInLangIds, $newTermInLangIds );
				$rowsToInsert = [];
				foreach ( $termInLangIdsToInsert as $termInLangIdToInsert ) {
					$rowsToInsert[] = [
						'wbit_item_id' => $itemId->getNumericId(),
						'wbit_term_in_lang_id' => $termInLangIdToInsert,
					];
				}

				$this->getDbw()->insert(
					'wbt_item_terms',
					$rowsToInsert,
					$fname
				);
			}
		);

		if ( $termInLangIdsToClean !== [] ) {
			// Delete entries in wbt_item_terms that are no longer needed
			// Further cleanup should then done by the caller of this method
			$this->getDbw()->delete(
				'wbt_item_terms',
				[
					'wbit_item_id' => $itemId->getNumericId(),
					'wbit_term_in_lang_id' => $termInLangIdsToClean,
				],
				__METHOD__
			);
		}

		return $termInLangIdsToClean;
	}

	public function deleteTerms( ItemId $itemId ) {
		MediaWikiServices::getInstance()->getStatsdDataFactory()->increment(
			'wikibase.repo.term_store.ItemTermStore_deleteTerms'
		);
		$this->assertCanWriteItemTerms();

		$termInLangIdsToClean = $this->deleteTermsWithoutClean( $itemId );
		if ( $termInLangIdsToClean !== [] ) {
			$this->cleanTermsIfUnused( $termInLangIdsToClean );
		}
	}

	/**
	 * Delete wbt_item_terms rows for the given item ID,
	 * and return term in lang IDs that are no longer referenced
	 * and might now need to be cleaned up.
	 *
	 * (The returned term in lang IDs might still be used in wbt_item_terms rows
	 * for other item IDs or elsewhere, and this should be checked just before cleanup.
	 * However, that may happen in a different transaction than this call.)
	 *
	 * @param ItemId $itemId
	 * @return int[]
	 */
	private function deleteTermsWithoutClean( ItemId $itemId ): array {
		$res = $this->getDbw()->select(
			'wbt_item_terms',
			[ 'wbit_id', 'wbit_term_in_lang_id' ],
			[ 'wbit_item_id' => $itemId->getNumericId() ],
			__METHOD__,
			[ 'FOR UPDATE' ]
		);

		$itemTermRowIdsToDelete = [];
		$termInLangIdsToCleanUp = [];
		foreach ( $res as $row ) {
			$itemTermRowIdsToDelete[] = $row->wbit_id;
			$termInLangIdsToCleanUp[] = $row->wbit_term_in_lang_id;
		}

		if ( $itemTermRowIdsToDelete !== [] ) {
			$this->getDbw()->delete(
				'wbt_item_terms',
				[ 'wbit_id' => $itemTermRowIdsToDelete ],
				__METHOD__
			);
		}

		return array_values( array_unique( $termInLangIdsToCleanUp ) );
	}

	/**
	 * Of the given term in lang IDs, delete those that aren’t used by any other items or properties.
	 *
	 * @param int[] $termInLangIds (wbtl_id)
	 */
	private function cleanTermsIfUnused( array $termInLangIds ) {
		$this->termInLangIdsCleaner->cleanTermInLangIds(
			$this->findActuallyUnusedTermInLangIds( $termInLangIds, $this->getDbw() )
		);
	}

	private function shouldWriteToItems() : bool {
		return $this->entitySource->getDatabaseName() === false;
	}

	private function assertItemsAreLocal() : void {
		if ( !$this->shouldWriteToItems() ) {
			throw new InvalidArgumentException(
				'This implementation cannot be used with remote entity sources!'
			);
		}
	}

	private function assertCanWriteItemTerms() {
		$this->assertItemsAreLocal();
		$this->assertUsingItemSource();
	}

	private function assertUsingItemSource() {
		if ( !in_array( Item::ENTITY_TYPE, $this->entitySource->getEntityTypes() ) ) {
			throw new InvalidArgumentException(
				$this->entitySource->getSourceName() . ' does not provided properties'
			);
		}
	}
}
