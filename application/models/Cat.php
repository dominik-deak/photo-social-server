<?php
	
class Cat extends CI_Model {
	private $cats = array(
			'https://farm9.staticflickr.com/8601/16458152469_3225c76e3b_q.jpg',
			'https://farm8.staticflickr.com/7023/6581178955_7e23af8bf9_q.jpg',
			'https://farm4.staticflickr.com/3354/3515160274_dd105e3d36_q.jpg',
			'https://farm4.staticflickr.com/3014/3100622156_3dfbb7e7b1_q.jpg',
			'https://farm8.staticflickr.com/7295/12939978493_b250e696a9_q.jpg',
			'https://farm3.staticflickr.com/2394/2052414325_b94e1a93d2_q.jpg',
			'https://farm5.staticflickr.com/4111/4973966926_63ab735344_q.jpg',
			'https://farm9.staticflickr.com/8065/8168159947_94b3a89a2a_q.jpg',
			'https://farm4.staticflickr.com/3902/14487842410_1c1f42df64_q.jpg',
			'https://farm4.staticflickr.com/3212/3100643316_13717856ca_q.jpg'
	);
	
	function getcat()
	{
		$ix = rand(0,9);
		return $this->cats[$ix];
	}
}