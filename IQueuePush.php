<?php
/**
 * IQueuePush 
 *	interface exposing services to a client trying to gain access
 *  into an abstract queue.
 *
 * @author Cristian Salazar H. <christiansalazarh@gmail.com> @salazarchris74 
 * @license FreeBSD {@link http://www.freebsd.org/copyright/freebsd-license.html}
 */
interface IQueuePush {
	public function iqueuepush_doauth($verbose=false);
	public function iqueuepush_push($queuename, $payload, $uuid='autogen');
	public function iqueuepush_read($queuename, $marker="",$uuid='autogen');
	public function iqueuepush_delete($queuename, $ids);
	public function iqueuepush_getbody($msg);
	public function iqueuepush_getid($msg);
}
