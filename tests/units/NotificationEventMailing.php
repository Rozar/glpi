<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2017 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
*/

namespace tests\units;

use \DbTestCase;

/* Test for inc/notificationeventajax.class.php */

class NotificationEventMailing extends DbTestCase {

   public function testGetTargetField() {
      $data = [];
      $this->string(\NotificationEventMailing::getTargetField($data))->isIdenticalTo('email');

      $expected = ['email' => null];
      $this->array($data)->isIdenticalTo($expected);

      $data = ['email' => 'user'];
      $this->string(\NotificationEventMailing::getTargetField($data))->isIdenticalTo('email');

      $expected = ['email' => null];
      $this->array($data)->isIdenticalTo($expected);

      $data = ['email' => 'user@localhost'];
      $this->string(\NotificationEventMailing::getTargetField($data))->isIdenticalTo('email');

      $expected = ['email' => 'user@localhost'];
      $this->array($data)->isIdenticalTo($expected);

      $uid = getItemByTypeName('User', TU_USER, true);
      $data = ['users_id' => $uid];

      $this->string(\NotificationEventMailing::getTargetField($data))->isIdenticalTo('email');
      $expected = [
         'users_id'  => $uid,
         'email'     => TU_USER . '@glpi.com'
      ];
      $this->array($data)->isIdenticalTo($expected);
   }

   public function testCanCron() {
      $this->boolean(\NotificationEventMailing::canCron())->isTrue();
   }

   public function testGetAdminData() {
      global $CFG_GLPI;

      $this->array(\NotificationEventMailing::getAdminData())
         ->isIdenticalTo([
            'email'     => $CFG_GLPI['admin_email'],
            'name'      => $CFG_GLPI['admin_email_name'],
            'language'  => $CFG_GLPI['language']
         ]);

      $CFG_GLPI['admin_email'] = 'adminlocalhost';
      $this->boolean(\NotificationEventMailing::getAdminData())->isFalse();
   }

   public function testGetEntityAdminsData() {
      $this->boolean(\NotificationEventMailing::getEntityAdminsData(0))->isFalse();

      $this->login();

      $entity1 = getItemByTypeName('Entity', '_test_child_1');
      $this->boolean(
         $entity1->update([
            'id'                 => $entity1->getId(),
            'admin_email'        => 'entadmin@localhost',
            'admin_email_name'   => 'Entity admin ONE'
         ])
      )->isTrue();

      $entity2 = getItemByTypeName('Entity', '_test_child_2');
      $this->boolean(
         $entity2->update([
            'id'                 => $entity2->getId(),
            'admin_email'        => 'entadmin2localhost',
            'admin_email_name'   => 'Entity admin TWO'
         ])
      )->isTrue();

      $this->array(\NotificationEventMailing::getEntityAdminsData($entity1->getID()))
         ->isIdenticalTo([
            [
               'language' => 'en_GB',
               'email' => 'entadmin@localhost',
               'name' => 'Entity admin ONE'
            ]
         ]);
      $this->boolean(\NotificationEventMailing::getEntityAdminsData($entity2->getID()))->isFalse();

      //reset
      $this->boolean(
         $entity1->update([
            'id'                 => $entity1->getId(),
            'admin_email'        => 'NULL',
            'admin_email_name'   => 'NULL'
         ])
      )->isTrue();
      $this->boolean(
         $entity2->update([
            'id'                 => $entity2->getId(),
            'admin_email'        => 'NULL',
            'admin_email_name'   => 'NULL'
         ])
      )->isTrue();
   }

   public function testRaise() {
      global $CFG_GLPI;

      //enable notifications
      $CFG_GLPI['use_notifications'] = 1;
      $CFG_GLPI['notifications_mailing'] = 1;

      $this->login();

      $ticket = new \Ticket();
      $ticket->notificationqueueonaction = false;
      $uid = getItemByTypeName('User', TU_USER, true);
      $this->integer(
         (int)$ticket->add([
            'name'                  => '',
            'description'           => 'My ticket to be notified.',
            '_users_id_requester'   => $uid,
            'content'               => ''
         ])
      )->isGreaterThan(0);

      //event has been raised; it is in the queue!
      $queue = getAllDatasFromTable('glpi_queuednotifications');

      $this->array($queue)->hasSize(2);

      $data = array_shift($queue);
      unset($data['id']);
      unset($data['create_time']);
      unset($data['send_time']);
      unset($data['messageid']);
      $data['body_text'] = preg_replace(
         '/(Opening date).+/m',
         '$1 OPENING',
         $data['body_text']
      );
      $data['body_html'] = preg_replace(
         '|(Opening date</span>).[^<]+(<br)|',
         '$1 OPENING $2',
         $data['body_html']
      );
      $expected = [
         'itemtype' => 'Ticket',
         'items_id' => $ticket->getID(),
         'notificationtemplates_id' => '4',
         'entities_id' => '0',
         'is_deleted' => '0',
         'sent_try' => '0',
         'sent_time' => null,
         'name' => '[GLPI #' . str_pad($ticket ->getID(), 7, '0', STR_PAD_LEFT).'] New ticket ',
         'sender' => 'admsys@localhost',
         'sendername' => '',
         'recipient' => '_test_user@glpi.com',
         'recipientname' => '_test_user',
         'replyto' => null,
         'replytoname' => null,
         'headers' => '{"Auto-Submitted":"auto-generated","X-Auto-Response-Suppress":"OOF, DR, NDR, RN, NRN"}',
         'body_html' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
                        \'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\'><html>
                        <head>
                         <META http-equiv=\'Content-Type\' content=\'text/html; charset=utf-8\'>
                         <title>[GLPI #'.str_pad($ticket ->getID(), 7, '0', STR_PAD_LEFT).'] New ticket </title>
                         <style type=\'text/css\'>
                           
                         </style>
                        </head>
                        <body>
<!-- description{ color: inherit; background: #ebebeb; border-style: solid;border-color: #8d8d8d; border-width: 0px 1px 1px 0px; }    -->
<div></div>
<div> URL : <a href="http://localhost:8088/index.php?redirect=ticket_'.$ticket->getID().'&amp;noAUTO=1">http://localhost:8088/index.php?redirect=ticket_'.$ticket->getID().'&amp;noAUTO=1</a> </div>
<p class="description b"><strong>Ticket: Description</strong></p>
<p><span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;"> Title</span>&#160;: <br /> <span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;"> Requesters</span>&#160;: _test_user      <br /> <span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;"> Opening date</span> OPENING <br /> <span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;"> Closing date</span>&#160;: <br /> <span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;"> Request source</span>&#160;:Helpdesk<br />
<br /><span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;"> Associated item</span>&#160;:
<p></p>
<br /> <span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;">Status </span>&#160;: New<br /> <br /> <span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;"> Urgency</span>&#160;: Medium<br /> <span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;"> Impact</span>&#160;: Medium<br /> <span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;"> Priority</span>&#160;: Medium <br />     <br />   No defined category     <br /> <span style="color: #8b8c8f; font-weight: bold; text-decoration: underline;"> Description</span>&#160;: </p>
<br /></p>
<div class="description b">Number of followups&#160;: 0</div>
<p></p>
<div class="description b">Number of tasks&#160;: 0</div>
<p></p><br><br>-- 
<br>SIGNATURE<br>Automatically generated by GLPI ' . GLPI_VERSION . '<br><br>

</body></html>',
         'body_text' => 'URL : http://localhost:8088/index.php?redirect=ticket_'.$ticket->getID().'&amp;noAUTO=1 

Ticket: Description

Title : 
 Requesters : _test_user 
 Opening date OPENING
 Closing date : 
 Request source : Helpdesk
Associated item :

Status : New

Urgency : Medium
 Impact : Medium
 Priority : Medium

No defined category 
 Description : 

Number of followups : 0

Number of tasks : 0

-- 
SIGNATURE
Automatically generated by GLPI ' . GLPI_VERSION . '

',
         'documents' => '',
         'mode' => 'mailing'
      ];
      $this->array($data)->isIdenticalTo($expected);

      //reset
      $CFG_GLPI['use_notifications'] = 0;
      $CFG_GLPI['notifications_mailing'] = 0;
   }
}
