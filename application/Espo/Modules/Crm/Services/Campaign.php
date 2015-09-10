<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2015 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 ************************************************************************/

namespace Espo\Modules\Crm\Services;

use \Espo\ORM\Entity;

class Campaign extends \Espo\Services\Record
{
    public function loadAdditionalFields($entity)
    {
        parent::loadAdditionalFields($entity);


        $sentCount = $this->getEntityManager()->getRepository('CampaignLogRecord')->where(array(
            'campaignId' => $entity->id,
            'action' => 'Sent'
        ))->count();
        $entity->set('sentCount', $sentCount);

        $openedCount = $this->getEntityManager()->getRepository('CampaignLogRecord')->where(array(
            'campaignId' => $entity->id,
            'action' => 'Opened'
        ))->count();
        $entity->set('openedCount', $openedCount);

        $clickedCount = $this->getEntityManager()->getRepository('CampaignLogRecord')->where(array(
            'campaignId' => $entity->id,
            'action' => 'Clicked'
        ))->count();
        $entity->set('clickedCount', $clickedCount);

        $optedOutCount = $this->getEntityManager()->getRepository('CampaignLogRecord')->where(array(
            'campaignId' => $entity->id,
            'action' => 'Opted Out'
        ))->count();
        $entity->set('optedOutCount', $optedOutCount);

        $bouncedCount = $this->getEntityManager()->getRepository('CampaignLogRecord')->where(array(
            'campaignId' => $entity->id,
            'action' => 'Bounced'
        ))->count();
        $entity->set('bouncedCount', $bouncedCount);

        $leadCreatedCount = $this->getEntityManager()->getRepository('Lead')->where(array(
            'campaignId' => $entity->id
        ))->count();
        $entity->set('leadCreatedCount', $leadCreatedCount);

        $entity->set('revenueCurrency', $this->getConfig()->get('defaultCurrency'));

        $params = array(
            'select' => array('SUM:amountConverted'),
            'whereClause' => array(
                'status' => 'Closed Won',
                'campaignId' => $entity->id
            ),
            'groupBy' => array('opportunity.campaignId')
        );

        $this->getEntityManager()->getRepository('Opportunity')->handleSelectParams($params);


        $sql = $this->getEntityManager()->getQuery()->createSelectQuery('Opportunity', $params);


        $pdo = $this->getEntityManager()->getPDO();
        $sth = $pdo->prepare($sql);
        $sth->execute();

        if ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            $revenue = floatval($row['SUM:amountConverted']);
            if ($revenue > 0) {
                $entity->set('revenue', $revenue);
            }
        }
    }

    public function logSent($campaignId, $queueItemId = null, Entity $target, Entity $emailOrEmailTemplate = null, $emailAddress, $actionDate = null)
    {
        if (empty($actionDate)) {
            $actionDate = date('Y-m-d H:i:s');
        }
        $logRecord = $this->getEntityManager()->getEntity('CampaignLogRecord');
        $logRecord->set(array(
            'campaignId' => $campaignId,
            'actionDate' => $actionDate,
            'parentId' => $target->id,
            'parentType' => $target->getEntityType(),
            'action' => 'Sent',
            'stringData' => $emailAddress,
            'queueItemId' => $queueItemId
        ));

        if ($emailOrEmailTemplate) {
            $logRecord->set(array(
                'objectId' => $emailOrEmailTemplate->id,
                'objectType' => $emailOrEmailTemplate->getEntityType()
            ));
        }
        $this->getEntityManager()->saveEntity($logRecord);
    }

    public function logBounced($campaignId, $queueItemId = null, Entity $target, $emailAddress, $isHard = false, $actionDate = null)
    {
        // TODO check for duplicate
        if (empty($actionDate)) {
            $actionDate = date('Y-m-d H:i:s');
        }
        $logRecord = $this->getEntityManager()->getEntity('CampaignLogRecord');
        $logRecord->set(array(
            'campaignId' => $campaignId,
            'actionDate' => $actionDate,
            'parentId' => $target->id,
            'parentType' => $target->getEntityType(),
            'action' => 'Bounced',
            'stringData' => $emailAddress,
            'queueItemId' => $queueItemId
        ));
        if ($isHard) {
            $logRecord->set('stringAdditionalData', 'Hard');
        } else {
            $logRecord->set('stringAdditionalData', 'Soft');
        }
        $this->getEntityManager()->saveEntity($logRecord);
    }

}

