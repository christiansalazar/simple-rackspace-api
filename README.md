simple-rackspace-api
====================

a simple interface to get access to services exposed in a cloud provided by www.rackspace.com

The official API provided by rackspace is here: 

	https://github.com/rackspace/php-opencloud

The official API Reference is located here:

	http://docs.rackspace.com/queues/api/v1.0/cq-devguide/content/overview.html

I know rackspace guys has their own well know API, but in my opinion it force
me to use php namespaces and the whole set of functions. In some cases
(like mine) the API provided by rackspace is overhelming my needs, so 
i decide to create a simple RETS api (CURL based) to interact with a
Rackspace Cloud API system.

The authentication is made by keeping in cache a copy of an Access Token
granted by rackspace, when cache expires or when the access expires then
a new access token is requested to rackspace.


```
	Model:

	[some_software_piece]-------{push queue data}------>[CloudQueues®]
										|					|
									  	|    [IQueuePush]<---
									 {using}
										|
										V
						[This So Nice Tool: RackspaceApi]
```

#Push data into the Queue:

```
	$api = new RackSpaceApi();
	$api->username = "some";
	$api->password = "your api key";

	$items = array();
	$items[] = array(300, base64_encode(json_encode($some_json_data_1)));
	..more items..

	if($api->iqueuepush_doauth(true))
		$api->iqueuepush->push("sample-queue",$items);
```

#Reading (and deleting) data from the Queue:

```
	$source = new RackSpaceApi();
	$source->username = "some";
	$source->password = "your api key";
	$marker = "";

	// 1. authentication
	if($source->iqueuepush_doauth(true)){

		// 2. fetch data from the queue, a marker is optional
		$items = $source->iqueuepush_read($qn,$marker);

		// 3. '$items' holds the json array returned by rackspace 
		if($data = json_decode($items)){
			if(isset($data->messages)){
				printf("reading...\n");
				$payload= array();
				$messages = array();
				foreach($data->messages as $msg){
					$msg_body = $source->iqueuepush_getbody($msg);
					$message_id = $source->iqueuepush_getid($msg);
					printf("reading message: %s\n",$message_id);
					$payload[] = json_decode(base64_decode($msg_body));
					$messages[] = $message_id;
				}
				do_whatever_you_want_with_payload($payload);
				printf("deleting messages..\n");
				$r = $source->iqueuepush_delete($qn, $messages);
				printf("messages deleted. result=%s\n",$r);
			}else
			printf("[BAD DATA IN QUEUE:]\n%s\n",print_r($data,true));
		}else
		printf("[NO DATA IN QUEUE]\n");
	}
```
