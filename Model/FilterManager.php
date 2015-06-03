<?php

namespace Mesd\UserBundle\Model;

class FilterManager {

    private $securityContext;
    private $objectManager;
    private $filterClass;
    private $roleClass;
    private $userClass;
    private $bypassRoles;
    private $config;

    public function __construct($securityContext, $objectManager, $userClass, $roleClass, $filterClass)
    {
        $this->securityContext = $securityContext;
        $this->objectManager   = $objectManager->getManager();
        $this->userClass       = $userClass;
        $this->roleClass       = $roleClass;
        $this->filterClass     = $filterClass;
    }

    public function applyFilters($queryBuilder, $filtersToApply)
    {
        $user = $this->securityContext->getToken()->getUser();
        foreach ($this->bypassRoles as $bypassRole) {
            if ($this->securityContext->isGranted($bypassRole)) {

                return $queryBuilder;
            }
        }

        $filters = $user->getFilter();
        $sortedCategories = array();

        foreach ($filters as $filter) {
            $category = $filter->getFilterCategory()->getName();
            if (in_array($category, $filtersToApply)) {
                if (array_key_exists($category, $sortedCategories)) {
                    $sortedCategories[$category][] = $filter;
                } else {
                    $sortedCategories[$category] = array($filter);
                }
            }
        }

        if ((0 === count($filters)) || (count($filtersToApply) != count($sortedCategories))) {
            $queryBuilder->andWhere('1 = 0');

            return $queryBuilder;
        }

        foreach ($sortedCategories as $sortedFilters) {
            $details = array();
            foreach ($sortedFilters as $filter) {
                $solventWrappers = $filter->getSolventWrappers();
                $detail = $this->getDetail($solventWrappers);
                $details[] = $detail;
            }
            $detail = '(' . implode(' OR ', $details) . ')';
            $queryBuilder = $this->applyFilter($queryBuilder, $solventWrappers[0], $detail);
        }

        return $queryBuilder;
    }

    protected function getDetail($solventWrappers) {
        $details = array();
        foreach ($solventWrappers as $solventWrapper) {
            $details[] = $solventWrapper->getDetails();
        }
        $detail = '(' . implode(' OR ', $details) . ')';

        return $detail;
    }

    protected function applyFilter($queryBuilder, $solventWrapper, $detail)
    {
        $queryBuilder = $solventWrapper->applyToQueryBuilder($queryBuilder, $detail);

        return $queryBuilder;
    }

    public function setBypassRoles( $bypassRoles )
    {
        $this->bypassRoles = $bypassRoles;

        return $this;
    }

    public function getBypassRoles()
    {
        return $this->bypassRoles;
    }

    public function setConfig( $config )
    {
        $this->config = $config;

        return $this;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getAsArray($filters, $metadataFactory)
    {
        $filterArray = array();
        foreach ($filters as $filter) {
            $solvents = $filter->getSolventWrappers();
            $solventArray = array();
            foreach ($solvents as $solvent) {
                $bunchArray = array();
                foreach ($solvent->getBunch() as $bunch) {
                    $entityArray = array();
                    foreach ($bunch->getEntity() as $entity) {
                        $metadata = $metadataFactory->getMetadataFor($entity->getName());
                        $joinArray = array();
                        foreach ($entity->getJoin() as $join) {
                            $associationMetadata = $metadata;
                            $associations = $join->getAssociation();
                            $length = count($associations);
                            for ($i = 0; $i < $length; $i++) {
                                 $targetEntity = $associationMetadata->getAssociationMapping($associations[$i])['targetEntity'];
                                 $associationMetadata = $metadataFactory->getMetadataFor($targetEntity);
                            }
                            $item = $this->objectManager->getRepository($associationMetadata->getName())->findOneById($join->getValue());
                            $joinArray[] = array(
                                'name' => $join->getName(),
                                'trail' => $join->getTrail(),
                                'item' => (string) $item,
                            );
                        }
                        $entityArray[] = array(
                            'name' => $entity->getName(),
                            'joins' => $joinArray,
                        );
                    }
                    $bunchArray[] = $entityArray;
                }
                $solventArray[] = $bunchArray;
            }

            $filterArray[] = array(
                'id'             => $filter->getId(),
                'filterCategory' => $filter->getFilterCategory(),
                'name'           => $filter->getName(),
                'solvent'        => $solventArray,
            );
        }

        return $filterArray;
    }

    public function getEntityLists($filterEntities, $metadataFactory)
    {
        $entityLists = array();
        foreach ($filterEntities as $entity) {
            $metadata = $metadataFactory->getMetadataFor($entity['entity']['name']);
            foreach ($entity['entity']['joins'] as $join) {
                if (!array_key_exists($join['name'], $entityLists)) {
                    $associations = explode('->', $join['trail']);
                    $associationMetadata = $metadata;
                    $length = count($associations);
                    for ($i = 0; $i < $length; $i++) {
                         $targetEntity = $associationMetadata->getAssociationMapping($associations[$i])['targetEntity'];
                         $associationMetadata = $metadataFactory->getMetadataFor($targetEntity);
                    }
                    $entities = $this->objectManager->getRepository($associationMetadata->getName())->findAll();
                    $entityLists[$join['name']] = $entities;
                }
            }
        }

        return $entityLists;
    }
}