<?php
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Shopware\CustomModels\CustomSort;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Model\ModelRepository;

class CustomSortRepository extends ModelRepository
{
    /**
     * Check if selected category has custom sorted products
     *
     * @param $categoryId
     * @return bool
     */
    public function hasCustomSort($categoryId)
    {
        $categoryId = (int) $categoryId;
        $builder = $this->getQueryBuilder();

        $builder->select('id')
            ->from('s_articles_sort', 'sort')
            ->where('categoryId = :categoryId')
            ->setParameter('categoryId', $categoryId);

        $result = (bool) $builder->execute()->fetchColumn();

        return $result;
    }

    /**
     * Return last sort position for selected category
     *
     * @param $categoryId
     * @return mixed
     */
    public function getMaxPosition($categoryId)
    {
        $categoryId = (int) $categoryId;
        $builder = $this->getQueryBuilder();

        $builder->select('MAX(position)')
            ->from('s_articles_sort', 'sort')
            ->where('categoryId = :categoryId')
            ->setParameter('categoryId', $categoryId);

        $max = $builder->execute()->fetchColumn();

        return $max;
    }

    /**
     * Return product list and exclude product containing passed ids for selected category
     *
     * @param int $categoryId
     * @param array $sortedProductsIds
     * @param int $orderBy
     * @param int|null $offset
     * @param int|null $limit
     * @return mixed
     */
    public function getArticleImageQuery($categoryId, $sortedProductsIds, $orderBy, $offset = null, $limit = null)
    {
        $builder = $this->getQueryBuilder();

        $builder->select(
            [
                'product.id as articleID',
                'product.name',
                'images.img as path',
                'images.extension',
            ]
        )
            ->from('s_articles', 'product')
            ->innerJoin('product', 's_articles_categories_ro', 'productCategory', 'productCategory.articleID = product.id')
            ->leftJoin('product', 's_articles_img', 'images', 'product.id = images.articleID')
            ->where('productCategory.categoryID = :categoryId')
            ->groupBy('product.id')
            ->setParameter('categoryId', $categoryId);

        if ($sortedProductsIds) {
            $builder->andWhere($builder->expr()->notIn("product.id", $sortedProductsIds));
        }

        if ($offset !== null && $limit !== null) {
            $builder->setFirstResult($offset)
                ->setMaxResults($limit);
        }

        $this->sortUnsortedByDefault($builder, $orderBy);

        return $builder;
    }

    /**
     * Get products from current category which are manually sorted
     *
     * @param int $categoryId
     * @param bool|false $linkedCategoryId
     * @return array
     */
    public function getSortedProducts($categoryId, $linkedCategoryId = false)
    {
        $categoryId = (int) $categoryId;
        $builder = $this->getQueryBuilder();

        $builder->select(
            [
                'sort.id as positionId',
                'product.id as articleID',
                'productDetail.ordernumber',
                'product.name',
                'images.img as path',
                'images.extension',
                'sort.position as position',
                'sort.position as oldPosition',
                'sort.pin as pin',
            ]
        )
            ->from('s_articles', 'product')
            ->innerJoin('product', 's_articles_categories_ro', 'productCategory', 'productCategory.articleID = product.id AND productCategory.categoryID IN (:categoryId)')
            ->innerJoin('product', 's_articles_details', 'productDetail', 'productDetail.articleID = product.id')
            ->leftJoin('product', 's_articles_img', 'images', 'product.id = images.articleID')
            ->where('sort.pin = 1')
            ->groupBy('product.id')
            ->orderBy('-sort.position', 'DESC')
            ->setParameter('categoryId', $categoryId);

        if ($linkedCategoryId !== false) {
            $builder->leftJoin('product', 's_articles_sort', 'sort', 'product.id = sort.articleId AND sort.categoryId = :linkedCategoryId OR sort.categoryId IS NULL')
                ->setParameter('linkedCategoryId', $linkedCategoryId);
        } else {
            $builder->leftJoin('product', 's_articles_sort', 'sort', 'product.id = sort.articleId AND (sort.categoryId = productCategory.categoryID OR sort.categoryId IS NULL)');
        }

        $result = $builder->execute()->fetchAll();

        return $result;
    }

    /**
     * Return total count of products in selected category
     *
     * @param $categoryId
     * @return mixed
     */
    public function getArticleImageCountQuery($categoryId)
    {
        $builder = $this->getQueryBuilder();

        $builder->select('COUNT(DISTINCT product.id) as Total')
            ->from('s_articles', 'product')
            ->innerJoin('product', 's_articles_categories_ro', 'productCategory', 'productCategory.articleID = product.id')
            ->leftJoin('product', 's_articles_img', 'images', 'product.id = images.articleID')
            ->where('productCategory.categoryID = :categoryId')
            ->setParameter('categoryId', $categoryId);

        return $builder;
    }

    /**
     * Sort products for current category by passed sort type
     *
     * @param QueryBuilder $builder
     * @param integer $orderBy
     */
    private function sortUnsortedByDefault($builder, $orderBy)
    {
        switch ($orderBy) {
            case 1:
                $builder->addOrderBy('product.datum', 'DESC')
                    ->addOrderBy('product.changetime', 'DESC');
                break;
            case 2:
                $builder->leftJoin('product', 's_articles_top_seller_ro', 'topSeller', 'topSeller.article_id = product.id')
                    ->addOrderBy('topSeller.sales', 'DESC')
                    ->addOrderBy('topSeller.article_id', 'DESC');
                break;
            case 3:
                $builder->addSelect('MIN(ROUND(defaultPrice.price * priceVariant.minpurchase * 1, 2)) as cheapest_price')
                    ->leftJoin('product', 's_articles_prices', 'defaultPrice', 'defaultPrice.articleID = product.id')
                    ->innerJoin('defaultPrice', 's_articles_details', 'priceVariant', 'priceVariant.id = defaultPrice.articledetailsID')
                    ->addOrderBy('cheapest_price', 'ASC')
                    ->addOrderBy('product.id', 'DESC');
                break;
            case 4:
                $builder->addSelect('MIN(ROUND(defaultPrice.price * priceVariant.minpurchase * 1, 2)) as cheapest_price')
                    ->leftJoin('product', 's_articles_prices', 'defaultPrice', 'defaultPrice.articleID = product.id')
                    ->innerJoin('defaultPrice', 's_articles_details', 'priceVariant', 'priceVariant.id = defaultPrice.articledetailsID')
                    ->addOrderBy('cheapest_price', 'DESC')
                    ->addOrderBy('product.id', 'DESC');
                break;
            case 5:
                $builder->addOrderBy('product.name', 'ASC');
                break;
            case 6:
                $builder->addOrderBy('product.name', 'DESC');
                break;
            case 7:
                $builder
                    ->addSelect('(SUM(vote.points) / COUNT(vote.id)) as votes')
                    ->leftJoin('product', 's_articles_vote', 'vote', 'product.id = vote.articleID')
                    ->addOrderBy('votes', 'DESC')
                    ->addOrderBy('product.id', 'DESC')
                    ->groupBy('product.id');
                break;
            case 9:
                $builder
                    ->innerJoin('product', 's_articles_details', 'variant', 'variant.id = product.main_detail_id')
                    ->addOrderBy('variant.instock', 'ASC')
                    ->addOrderBy('product.id', 'DESC');
                break;
            case 10:
                $builder
                    ->innerJoin('product', 's_articles_details', 'variant', 'variant.id = product.main_detail_id')
                    ->addOrderBy('variant.instock', 'DESC')
                    ->addOrderBy('product.id', 'DESC');
                break;

        }
    }

    /**
     * Sets pin value to 0
     *
     * @param $id - the id of the s_articles_sort record
     */
    public function unpinById($id)
    {
        $builder = $this->getQueryBuilder();

        $builder->update('s_articles_sort')
            ->set('pin', 0)
            ->where('id = :id')
            ->setParameter('id', $id);

        $builder->execute();
    }

    /**
     * Deletes all records, which are unpinned, until the pinned record with max position
     *
     * @param $categoryId
     */
    public function deleteUnpinnedRecords($categoryId)
    {
        $maxPinPosition = $this->getMaxPinPosition($categoryId);
        if ($maxPinPosition === null) {
            $maxPinPosition = 0;
        }

        $builder = $this->getQueryBuilder();

        $builder->delete('s_articles_sort')
            ->where('categoryId = :categoryId')
            ->andWhere('position >= :maxPinPosition')
            ->andWhere('pin = 0')
            ->setParameter('categoryId', $categoryId)
            ->setParameter(':maxPinPosition', $maxPinPosition);

        $builder->execute();
    }

    /**
     * Returns the position of the pinned record with max position
     *
     * @param $categoryId
     * @return mixed
     */
    public function getMaxPinPosition($categoryId)
    {
        $builder = $this->getQueryBuilder();

        $builder->select(['MAX(position) AS maxPinPosition'])
            ->from('s_articles_sort', 'sort')
            ->where('categoryId = :categoryId')
            ->andWhere('pin = 1')
            ->orderBy('position', 'DESC')
            ->setParameter('categoryId', $categoryId);

        $maxPinPosition = $builder->execute()->fetchColumn();

        return $maxPinPosition;
    }

    /**
     * Returns product position for selected product
     *
     * @param $articleId
     * @return mixed
     */
    public function getPositionByArticleId($articleId)
    {
        $builder = $this->getQueryBuilder();

        $builder->select(['position'])
            ->from('s_articles_sort', 'sort')
            ->where('articleId = :articleId')
            ->setParameter('articleId', $articleId);

        $position = $builder->execute()->fetchColumn();

        return $position;
    }

    /**
     * Returns last deleted position of product for selected category
     *
     * @param $categoryId
     * @return mixed
     */
    public function getPositionOfDeletedProduct($categoryId)
    {
        $builder = $this->getQueryBuilder();

        $builder->select(['swag_deleted_position'])
            ->from('s_categories_attributes', 'categories_attributes')
            ->where('categoryID = :categoryId')
            ->setParameter('categoryId', $categoryId);

        $deletedPosition = $builder->execute()->fetchColumn();

        return $deletedPosition;
    }

    /**
     * Delete custom sort flag for selected category
     *
     * @param $categoryId
     */
    public function resetDeletedPosition($categoryId)
    {
        $builder = $this->getQueryBuilder();

        $builder->update('s_categories_attributes')
            ->set('swag_deleted_position', 'null')
            ->where('categoryID = :categoryId')
            ->setParameter('categoryId', $categoryId);

        $builder->execute();
    }

    /**
     * Update category attributes for selected category
     *
     * @param $categoryId
     * @param $baseSort
     * @param null $categoryLink
     * @param null $defaultSort
     */
    public function updateCategoryAttributes($categoryId, $baseSort, $categoryLink = null, $defaultSort = null)
    {
        $builder = $this->getQueryBuilder();

        $builder->update('s_categories_attributes')
            ->where('categoryID = :categoryId')
            ->setParameter('categoryId', $categoryId);

        if ($baseSort != 0) {
            $builder->set('swag_base_sort', $baseSort);
        }

        if ($categoryLink !== null) {
            $builder->set('swag_link', $categoryLink);
        }

        if ($defaultSort !== null) {
            $builder->set('swag_show_by_default', $defaultSort);
        }

        $builder->execute();
    }

    /**
     * @return QueryBuilder
     */
    private function getQueryBuilder()
    {
        /** @var ModelManager $em */
        $em = $this->getEntityManager();
        /** @var QueryBuilder $builder */
        $builder = $em->getDBALQueryBuilder();

        return $builder;
    }
}
