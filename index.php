<?php

//IMathAS:  Alt main page
//(c) 2010 David Lippman

/*** master includes ***/
require("./validate.php");

//0: classes you're teaching
//1: classes you're tutoring
//2: classes you're taking
//10: messages widget
//11: forum posts widget

//pagelayout:  array of arrays.  pagelayout[0] = fullwidth header, [1] = left bar 25%, [2] = rigth bar 75%
//[3]: 0 for newmsg note next to courses, 1 for newpost note next to courses
//DB $query = "SELECT homelayout,hideonpostswidget FROM imas_users WHERE id='$userid'";
//DB $result = mysql_query($query) or die("Query failed : " . mysql_error());
//DB list($homelayout,$hideonpostswidget) = mysql_fetch_row($result);
$stm = $DBH->prepare("SELECT homelayout,hideonpostswidget FROM imas_users WHERE id=:id");
$stm->execute(array(':id'=>$userid));
list($homelayout,$hideonpostswidget) = $stm->fetch(PDO::FETCH_NUM);
if ($hideonpostswidget!='') {
	$hideonpostswidget = explode(',',$hideonpostswidget);
} else {
	$hideonpostswidget = array();
}

$pagelayout = explode('|',$homelayout);
foreach($pagelayout as $k=>$v) {
	if ($v=='') {
		$pagelayout[$k] = array();
	} else {
		$pagelayout[$k] = explode(',',$v);
	}
}
//$pagelayout = array(array(),array(0,1,2),array(10,11),array());

$shownewmsgnote = in_array(0,$pagelayout[3]);
$shownewpostnote = in_array(1,$pagelayout[3]);

$showmessagesgadget = (in_array(10,$pagelayout[1]) || in_array(10,$pagelayout[0]) || in_array(10,$pagelayout[2]));
$showpostsgadget = (in_array(11,$pagelayout[1]) || in_array(11,$pagelayout[0]) || in_array(11,$pagelayout[2]));

$twocolumn = (count($pagelayout[1])>0 && count($pagelayout[2])>0);

$placeinhead = '
  <style type="text/css">
   div.pagetitle h2 {
  	margin-top: 0px;
  	}
   #homefullwidth { clear: both;}
  </style>';
$placeinhead .= "<script type=\"text/javascript\" src=\"$imasroot/javascript/tablesorter.js\"></script>\n";
$placeinhead .= '<script type="text/javascript">$(function() {
  var html = \'<div class="coursedd dropdown"><a role="button" tabindex=0 class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><img src="img/gears.png" alt="Options"/></a>\';
  html += \'<ul role="menu" class="dropdown-menu dropdown-menu-right">\';
  $(".courselist-teach li").css("clear","both").each(function (i,el) {
  	if ($(el).attr("data-isowner")=="true") {
  		var cid = $(el).attr("data-cid");
  		var thishtml = html + \' <li><a href="admin/forms.php?from=home&action=modify&id=\'+cid+\'">'._('Settings').'</a></li>\';
  		thishtml += \' <li><a href="#" onclick="hidefromcourselist(this,\'+cid+\',\\\'teach\\\');">'._('Hide from course list').'</a></li>\';
  		thishtml += \' <li><a href="admin/forms.php?from=home&action=chgteachers&id=\'+cid+\'">'._('Add/remove teachers').'</a></li>\';
  		thishtml += \' <li><a href="admin/forms.php?from=home&action=transfer&id=\'+cid+\'">'._('Transfer ownership').'</a></li>\';
  		thishtml += \' <li><a href="admin/forms.php?from=home&action=delete&id=\'+cid+\'">'._('Delete').'</a></li>\';
  		thishtml += \'</ul></div>\';
  		$(el).append(thishtml);
  	} 
  });
  $(".dropdown-toggle").dropdown();
  });
  </script>';
$nologo = true;


//check for new posts - do for each type if there are courses
$newpostscnt = array();
$postcheckcids = array();
$postcheckstucids = array();
$page_coursenames = array();
$page_newpostlist = array();

$newmsgcnt = array();
if ($showmessagesgadget) {
	$page_newmessagelist = array();
	//DB $query = "SELECT imas_msgs.id,imas_msgs.title,imas_msgs.senddate,imas_users.LastName,imas_users.FirstName,imas_msgs.courseid ";
	//DB $query .= "FROM imas_msgs LEFT JOIN imas_users ON imas_users.id=imas_msgs.msgfrom WHERE ";
	//DB $query .= "imas_msgs.msgto='$userid' AND (imas_msgs.isread=0 OR imas_msgs.isread=4)";
	//DB $query .= "ORDER BY senddate DESC ";
	//DB $result = mysql_query($query) or die("Query failed : $query " . mysql_error());
	//DB while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$query = "SELECT imas_msgs.id,imas_msgs.title,imas_msgs.senddate,imas_users.LastName,imas_users.FirstName,imas_msgs.courseid ";
	$query .= "FROM imas_msgs LEFT JOIN imas_users ON imas_users.id=imas_msgs.msgfrom WHERE ";
	$query .= "imas_msgs.msgto=:msgto AND (imas_msgs.isread=0 OR imas_msgs.isread=4) ";
	$query .= "ORDER BY senddate DESC ";
	$stm = $DBH->prepare($query);
	$stm->execute(array(':msgto'=>$userid));
	while ($line = $stm->fetch(PDO::FETCH_ASSOC)) {
		if (!isset($newmsgcnt[$line['courseid']])) {
			$newmsgcnt[$line['courseid']] = 1;
		} else {
			$newmsgcnt[$line['courseid']]++;
		}
		$page_newmessagelist[] = $line;
	}
} else {
	//check for new messages

	//DB $query = "SELECT courseid,COUNT(id) FROM imas_msgs WHERE msgto='$userid' AND (isread=0 OR isread=4) GROUP BY courseid";
	//DB $result = mysql_query($query) or die("Query failed : " . mysql_error());
	//DB while ($row = mysql_fetch_row($result)) {
	$stm = $DBH->prepare("SELECT courseid,COUNT(id) FROM imas_msgs WHERE msgto=:msgto AND (isread=0 OR isread=4) GROUP BY courseid");
	$stm->execute(array(':msgto'=>$userid));
	while ($row = $stm->fetch(PDO::FETCH_NUM)) {
		$newmsgcnt[$row[0]] = $row[1];
	}
}

$page_studentCourseData = array();

// check to see if the user is enrolled as a student
//DB $query = "SELECT imas_courses.name,imas_courses.id,imas_students.hidefromcourselist FROM imas_students,imas_courses ";
//DB $query .= "WHERE imas_students.courseid=imas_courses.id AND imas_students.userid='$userid' ";
//DB $query .= "AND (imas_courses.available=0 OR imas_courses.available=2) ORDER BY imas_courses.name";
//DB $result = mysql_query($query) or die("Query failed : " . mysql_error());
$query = "SELECT imas_courses.name,imas_courses.id,imas_students.hidefromcourselist FROM imas_students,imas_courses ";
$query .= "WHERE imas_students.courseid=imas_courses.id AND imas_students.userid=:userid ";
$query .= "AND (imas_courses.available=0 OR imas_courses.available=2) ORDER BY imas_courses.name";
$stm = $DBH->prepare($query);
$stm->execute(array(':userid'=>$userid));
$stuhashiddencourses = false;
//DB if (mysql_num_rows($result)==0) {
if ($stm->rowCount()==0) {
	$noclass = true;
} else {
	//DB while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
	while ($line = $stm->fetch(PDO::FETCH_ASSOC)) {
		if ($line['hidefromcourselist']==1) {
			$stuhashiddencourses = true;
		} else {
			$noclass = false;
			$page_studentCourseData[] = $line;
			$page_coursenames[$line['id']] = $line['name'];
			if (!in_array($line['id'],$hideonpostswidget)) {
				$postcheckstucids[] = $line['id'];
			}
		}
	}
}

$page_teacherCourseData = array();
if ($myrights>10) {
	// check to see if the user is enrolled as a teacher
	//DB $query = "SELECT imas_courses.name,imas_courses.id,imas_courses.available,imas_courses.lockaid FROM imas_teachers,imas_courses ";
	//DB $query .= "WHERE imas_teachers.courseid=imas_courses.id AND imas_teachers.userid='$userid' ";
	//DB $query .= "AND (imas_courses.available=0 OR imas_courses.available=1) ORDER BY imas_courses.name";
	//DB $result = mysql_query($query) or die("Query failed : " . mysql_error());
	//DB if (mysql_num_rows($result)==0) {
	$query = "SELECT imas_courses.name,imas_courses.id,imas_courses.available,imas_courses.lockaid,imas_courses.ownerid,imas_teachers.hidefromcourselist FROM imas_teachers,imas_courses ";
	$query .= "WHERE imas_teachers.courseid=imas_courses.id AND imas_teachers.userid=:userid ";
	$query .= "AND (imas_courses.available=0 OR imas_courses.available=1) ORDER BY imas_courses.name";
	$stm = $DBH->prepare($query);
	$stm->execute(array(':userid'=>$userid));
	$teachhashiddencourses = false;
	if ($stm->rowCount()==0) {
		$noclass = true;
	} else {
		//DB while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
		while ($line = $stm->fetch(PDO::FETCH_ASSOC)) {
			if ($line['hidefromcourselist']==1) {
				$teachhashiddencourses = true;
			} else {
				$noclass = false;
				$page_teacherCourseData[] = $line;
				$page_coursenames[$line['id']] = $line['name'];
				if (!in_array($line['id'],$hideonpostswidget)) {
					$postcheckcids[] = $line['id'];
				}
			}
		}
	}
}

$page_tutorCourseData = array();
// check to see if the user is enrolled as a tutor
//DB $query = "SELECT imas_courses.name,imas_courses.id,imas_courses.available,imas_courses.lockaid FROM imas_tutors,imas_courses ";
//DB $query .= "WHERE imas_tutors.courseid=imas_courses.id AND imas_tutors.userid='$userid' ";
//DB $query .= "AND (imas_courses.available=0 OR imas_courses.available=1) ORDER BY imas_courses.name";
//DB $result = mysql_query($query) or die("Query failed : " . mysql_error());
//DB if (mysql_num_rows($result)==0) {
$query = "SELECT imas_courses.name,imas_courses.id,imas_courses.available,imas_courses.lockaid,imas_tutors.hidefromcourselist FROM imas_tutors,imas_courses ";
$query .= "WHERE imas_tutors.courseid=imas_courses.id AND imas_tutors.userid=:userid ";
$query .= "AND (imas_courses.available=0 OR imas_courses.available=1) ORDER BY imas_courses.name";
$stm = $DBH->prepare($query);
$stm->execute(array(':userid'=>$userid));
$tutorhashiddencourses = false;
if ($stm->rowCount()==0) {
	$noclass = true;
} else {
	//DB while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
	while ($line = $stm->fetch(PDO::FETCH_ASSOC)) {
		if ($line['hidefromcourselist']==1) {
			$tutorhashiddencourses = true;
		} else {
			$noclass = false;
			$page_tutorCourseData[] = $line;
			$page_coursenames[$line['id']] = $line['name'];
			if (!in_array($line['id'],$hideonpostswidget)) {
				$postcheckstucids[] = $line['id'];
			}
		}
	}
}

//get new posts
//check for new posts in courses being taught.
//TODO:  changed since wasn't being hit before. Check it works right.
//  is the cost of calling this more correct version more than the benefit of
//  getting notified of group posts correctly?
$postcidlist = implode(',',$postcheckcids);
$postthreads = array();
if ($showpostsgadget && count($postcheckcids)>0) {
	/*$query = "SELECT imas_forums.name,imas_forums.id,imas_forum_threads.id as threadid,imas_forum_threads.lastposttime,imas_forums.courseid FROM imas_forum_threads ";
	$query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id LEFT JOIN imas_forum_views AS mfv ";
	$query .= "ON mfv.threadid=imas_forum_threads.id AND mfv.userid='$userid' WHERE imas_forums.courseid IN ($postcidlist) AND imas_forums.grpaid=0 ";
	$query .= "AND (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL)) ORDER BY imas_forum_threads.lastposttime DESC";*/

	//DB $query = "SELECT imas_forums.name,imas_forums.id,imas_forum_threads.id as threadid,imas_forum_threads.lastposttime,imas_forums.courseid FROM imas_forum_threads ";
	//DB $query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id ";
	//DB $query .= "AND imas_forums.courseid IN ($postcidlist) ";
	//DB $query .= "LEFT JOIN imas_forum_views as mfv ON mfv.threadid=imas_forum_threads.id AND mfv.userid='$userid' ";
	//DB $query .= "WHERE (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL)) ";
	//DB $query .= "ORDER BY imas_forum_threads.lastposttime DESC";
	//DB $result = mysql_query($query) or die("Query failed : $query " . mysql_error());
	//DB while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$query = "SELECT imas_forums.name,imas_forums.id,imas_forum_threads.id as threadid,imas_forum_threads.lastposttime,imas_forums.courseid FROM imas_forum_threads ";
	$query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id ";
	$query .= "AND imas_forums.courseid IN ($postcidlist) ";  //is int's from DB - safe
	$query .= "LEFT JOIN imas_forum_views as mfv ON mfv.threadid=imas_forum_threads.id AND mfv.userid=:userid ";
	$query .= "WHERE (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL)) ";
	$query .= "ORDER BY imas_forum_threads.lastposttime DESC";
	$stm = $DBH->prepare($query);
	$stm->execute(array(':userid'=>$userid));
	while ($line = $stm->fetch(PDO::FETCH_ASSOC)) {
		if (!isset($newpostcnt[$line['courseid']])) {
			$newpostcnt[$line['courseid']] = 1;
		} else {
			$newpostcnt[$line['courseid']]++;
		}
		if ($newpostcnt[$line['courseid']]<10) {
			$page_newpostlist[] = $line;
			$postthreads[] = $line['threadid'];
		}
	}
} else if (count($postcheckcids)>0) {
	/*$query = "SELECT courseid,count(*) FROM ";
	$query .= "(SELECT imas_forums.courseid,imas_forum_threads.id FROM imas_forum_threads ";
	$query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id LEFT JOIN imas_forum_views AS mfv ";
	$query .= "ON mfv.threadid=imas_forum_threads.id AND mfv.userid='$userid' WHERE imas_forums.courseid IN ";
	$query .= "($postcidlist) AND imas_forums.grpaid=0 ";
	$query .= "AND (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL))) AS newitems ";
	$query .= "GROUP BY courseid";*/

	$now = time();
	//DB $query = "SELECT imas_forums.courseid, COUNT(imas_forum_threads.id) FROM imas_forum_threads ";
	//DB $query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id ";
	//DB $query .= "AND imas_forums.courseid IN ($postcidlist) ";
	//DB $query .= "LEFT JOIN imas_forum_views as mfv ON mfv.threadid=imas_forum_threads.id AND mfv.userid='$userid' ";
	//DB $query .= "WHERE (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL)) ";
	//DB $query .= "AND (imas_forum_threads.stugroupid=0 OR imas_forum_threads.stugroupid IN (SELECT stugroupid FROM imas_stugroupmembers WHERE userid='$userid')) ";
	//DB $query .= "GROUP BY imas_forums.courseid";
	//DB $r2 = mysql_query($query) or die("Query failed : $query " . mysql_error());
	//DB while ($row = mysql_fetch_row($r2)) {
	$query = "SELECT imas_forums.courseid, COUNT(imas_forum_threads.id) FROM imas_forum_threads ";
	$query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id ";
	$query .= "AND imas_forums.courseid IN ($postcidlist) ";    //is int's from DB - safe
	$query .= "LEFT JOIN imas_forum_views as mfv ON mfv.threadid=imas_forum_threads.id AND mfv.userid=:userid ";
	$query .= "WHERE (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL)) ";
	//this is not consistent with above...
	//$query .= "AND (imas_forum_threads.stugroupid=0 OR imas_forum_threads.stugroupid IN (SELECT stugroupid FROM imas_stugroupmembers WHERE userid=:useridB)) ";
	$query .= "GROUP BY imas_forums.courseid";
	$stm2 = $DBH->prepare($query);
	$stm2->execute(array(':userid'=>$userid));
	while ($row = $stm2->fetch(PDO::FETCH_NUM)) {
		$newpostcnt[$row[0]] = $row[1];
	}
}
//check for new posts in courses being taken
$poststucidlist = implode(',',$postcheckstucids);
if ($showpostsgadget && count($postcheckstucids)>0) {
	/*$query = "SELECT imas_forums.name,imas_forums.id,imas_forum_threads.id as threadid,imas_forum_threads.lastposttime,imas_forums.courseid FROM imas_forum_threads ";
	$query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id LEFT JOIN imas_forum_views AS mfv ";
	$query .= "ON mfv.threadid=imas_forum_threads.id AND mfv.userid='$userid' WHERE imas_forums.courseid IN ($postcidlist) AND imas_forums.grpaid=0 ";
	$query .= "AND (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL)) ORDER BY imas_forum_threads.lastposttime DESC";*/
	$now = time();
	//DB $query = "SELECT imas_forums.name,imas_forums.id,imas_forum_threads.id as threadid,imas_forum_threads.lastposttime,imas_forums.courseid FROM imas_forum_threads ";
	//DB $query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id ";
	//DB $query .= "AND (imas_forums.avail=2 OR (imas_forums.avail=1 AND imas_forums.startdate<$now && imas_forums.enddate>$now)) ";
	//DB $query .= "AND imas_forums.courseid IN ($poststucidlist) ";
	//DB $query .= "LEFT JOIN imas_forum_views as mfv ON mfv.threadid=imas_forum_threads.id AND mfv.userid='$userid' ";
	//DB $query .= "WHERE (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL)) ";
	//DB $query .= "AND (imas_forum_threads.stugroupid=0 OR imas_forum_threads.stugroupid IN (SELECT stugroupid FROM imas_stugroupmembers WHERE userid='$userid')) ";
	//DB $query .= "ORDER BY imas_forum_threads.lastposttime DESC";
	//DB $result = mysql_query($query) or die("Query failed : $query " . mysql_error());
	//DB while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$query = "SELECT imas_forums.name,imas_forums.id,imas_forum_threads.id as threadid,imas_forum_threads.lastposttime,imas_forums.courseid FROM imas_forum_threads ";
	$query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id ";
	$query .= "AND (imas_forums.avail=2 OR (imas_forums.avail=1 AND imas_forums.startdate<$now && imas_forums.enddate>$now)) ";
	$query .= "AND imas_forums.courseid IN ($poststucidlist) "; //is int's from DB - safe
	$query .= "LEFT JOIN imas_forum_views as mfv ON mfv.threadid=imas_forum_threads.id AND mfv.userid=:userid ";
	$query .= "WHERE (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL)) ";
	$query .= "AND (imas_forum_threads.stugroupid=0 OR imas_forum_threads.stugroupid IN (SELECT stugroupid FROM imas_stugroupmembers WHERE userid=:useridB)) ";
	$query .= "ORDER BY imas_forum_threads.lastposttime DESC";
	$stm = $DBH->prepare($query);
	$stm->execute(array(':userid'=>$userid, ':useridB'=>$userid));
	while ($line = $stm->fetch(PDO::FETCH_ASSOC)) {
		if (!isset($newpostcnt[$line['courseid']])) {
			$newpostcnt[$line['courseid']] = 1;
		} else {
			$newpostcnt[$line['courseid']]++;
		}
		if ($newpostcnt[$line['courseid']]<10) {
			$page_newpostlist[] = $line;
			$postthreads[] = $line['threadid'];
		}
	}
} else if (count($postcheckstucids)>0) {
	$now = time();
	/*
	$query = "SELECT courseid,count(*) FROM ";
	$query .= "(SELECT imas_forums.courseid,imas_forum_threads.id FROM imas_forum_threads ";
	$query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id LEFT JOIN imas_forum_views AS mfv ";
	$query .= "ON mfv.threadid=imas_forum_threads.id AND mfv.userid='$userid' WHERE imas_forums.courseid IN ";
	$query .= "($postcidlist) AND imas_forums.grpaid=0 ";
	$query .= "AND (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL))) AS newitems ";
	$query .= "GROUP BY courseid";*/
	//DB $query = "SELECT imas_forums.courseid, COUNT(imas_forum_threads.id) FROM imas_forum_threads ";
	//DB $query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id ";
	//DB $query .= "AND (imas_forums.avail=2 OR (imas_forums.avail=1 AND imas_forums.startdate<$now && imas_forums.enddate>$now)) ";
	//DB $query .= "AND imas_forums.courseid IN ($poststucidlist) ";
	//DB $query .= "LEFT JOIN imas_forum_views as mfv ON mfv.threadid=imas_forum_threads.id AND mfv.userid='$userid' ";
	//DB $query .= "WHERE (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL)) ";
	//DB $query .= "AND (imas_forum_threads.stugroupid=0 OR imas_forum_threads.stugroupid IN (SELECT stugroupid FROM imas_stugroupmembers WHERE userid='$userid')) ";
	//DB $query .= "GROUP BY imas_forums.courseid";
	//DB $r2 = mysql_query($query) or die("Query failed : $query" . mysql_error());
	//DB while ($row = mysql_fetch_row($r2)) {
	$query = "SELECT imas_forums.courseid, COUNT(imas_forum_threads.id) FROM imas_forum_threads ";
	$query .= "JOIN imas_forums ON imas_forum_threads.forumid=imas_forums.id ";
	$query .= "AND (imas_forums.avail=2 OR (imas_forums.avail=1 AND imas_forums.startdate<$now && imas_forums.enddate>$now)) ";
	$query .= "AND imas_forums.courseid IN ($poststucidlist) "; //int's from DB - safe
	$query .= "LEFT JOIN imas_forum_views as mfv ON mfv.threadid=imas_forum_threads.id AND mfv.userid=:userid ";
	$query .= "WHERE (imas_forum_threads.lastposttime>mfv.lastview OR (mfv.lastview IS NULL)) ";
	$query .= "AND (imas_forum_threads.stugroupid=0 OR imas_forum_threads.stugroupid IN (SELECT stugroupid FROM imas_stugroupmembers WHERE userid=:useridB)) ";
	$query .= "GROUP BY imas_forums.courseid";
	$stm2 = $DBH->prepare($query);
	$stm2->execute(array(':userid'=>$userid, ':useridB'=>$userid));
	while ($row = $stm2->fetch(PDO::FETCH_NUM)) {
		$newpostcnt[$row[0]] = $row[1];
	}
}
if ($myrights==100) {
	$brokencnt = array();
	//DB $query = "SELECT userights,COUNT(id) FROM imas_questionset WHERE broken=1 AND deleted=0 GROUP BY userights";
	//DB $result = mysql_query($query) or die("Query failed : $query" . mysql_error());
	//DB while ($row = mysql_fetch_row($result)) {
	$stm = $DBH->query("SELECT userights,COUNT(id) FROM imas_questionset WHERE broken=1 AND deleted=0 GROUP BY userights");
	while ($row = $stm->fetch(PDO::FETCH_NUM)) {
		$brokencnt[$row[0]] = $row[1];
	}
}

/*** done pulling stuff.  Time to display something ***/
require("header.php");
$msgtotal = array_sum($newmsgcnt);
echo '<div class="floatright" id="homelinkbox" role="navigation" aria-label="'._('Site tools').'">';
if (!isset($CFG['GEN']['hidedefindexmenu'])) {
	if ($myrights>5) {
		echo "<a href=\"forms.php?action=chguserinfo\">", _('Change User Info'), "</a> | \n";
		echo "<a href=\"forms.php?action=chgpwd\">", _('Change Password'), "</a> | \n";
	}
	echo '<a href="actions.php?action=logout">', _('Log Out'), '</a><br/>';
}
echo '<a href="msgs/msglist.php?cid=0">', _('Messages'), '</a>';
if ($msgtotal>0) {
	echo ' <a href="msgs/newmsglist.php?cid=0" class="noticetext">', sprintf(_('New (%d)'), $msgtotal), '</a>';
}
if ($myrights > 10) {
	echo " | <a href=\"docs/docs.php\">", _('Documentation'), "</a>\n";
} else if ($myrights > 9) {
	echo " | <a href=\"help.php?section=usingimas\">", _('Help'), "</a>\n";
}
if ($myrights >=75) {
	echo '<br/><a href="admin/admin.php">'._('Admin Page').'</a>';
}

echo '</div>';
echo '<div class="pagetitle" id="headerhome" role="banner"><h2>';
if (isset($CFG['GEN']['hometitle'])) {
	echo $CFG['GEN']['hometitle'];
} else {
	echo _('Welcome to'), " $installname, $userfullname";
}
echo '</h2>';
if ($myrights==100 && count($brokencnt)>0) {
	echo '<span class="noticetext">'.array_sum($brokencnt).'</span> questions, '.(array_sum($brokencnt)-$brokencnt[0]).' public, reported broken systemwide';
}
echo '</div>';


for ($i=0; $i<3; $i++) {
	if ($i==0) {
		echo '<div id="homefullwidth">';
	}
	if ($twocolumn) {
		if ($i==1) {
			echo '<div id="leftcolumn">';
		} else if ($i==2) {
			echo '<div id="rightcolumn">';
		}
	}
	for ($j=0; $j<count($pagelayout[$i]); $j++) {
		switch ($pagelayout[$i][$j]) {
			case 0:
				if ($myrights>10) {
					printCourses($page_teacherCourseData,_('Courses you\'re teaching'),'teach',$teachhashiddencourses);
				}
				break;
			case 1:
				printCourses($page_tutorCourseData,_('Courses you\'re tutoring'),'tutor',$tutorhashiddencourses);
				break;
			case 2:
				printCourses($page_studentCourseData,_('Courses you\'re taking'),'take',$stuhashiddencourses);
				break;
			case 10:
				printMessagesGadget();
				break;
			case 11:
				printPostsGadget();
				break;
		}
	}
	if ($i==2 || $twocolumn) {
		echo '</div>';
	}
}

require('./footer.php');


function printCourses($data,$title,$type=null,$hashiddencourses=false) {
	global $shownewmsgnote, $shownewpostnote, $imasroot,$userid;
	if (count($data)==0 && $type=='tutor') {return;}
	global $myrights,$showmessagesgadget,$showpostsgadget,$newmsgcnt,$newpostcnt;
	echo '<div role="navigation" aria-label="'.$title.'">';
	echo '<div class="block"><h3>'.$title.'</h3></div>';
	echo '<div class="blockitems"><ul class="nomark courselist courselist-'.$type.'">';
	for ($i=0; $i<count($data); $i++) {
		echo '<li';
		if ($type=='teach' && $myrights>39) {
			echo ' data-isowner="'.($data[$i]['ownerid']==$userid?'true':'false').'"';
			echo ' data-cid="'.$data[$i]['id'].'"';
		}
		echo '>'; 
		echo '<a href="course/course.php?folder=0&cid='.$data[$i]['id'].'">';
		echo $data[$i]['name'].'</a>';
		if (isset($data[$i]['available']) && (($data[$i]['available']&1)==1)) {
			echo ' <span style="color:green;">', _('Hidden'), '</span>';
		}
		if (isset($data[$i]['lockaid']) && $data[$i]['lockaid']>0) {
			echo ' <span style="color:green;">', _('Lockdown'), '</span>';
		}
		if ($shownewmsgnote && isset($newmsgcnt[$data[$i]['id']]) && $newmsgcnt[$data[$i]['id']]>0) {
			echo ' <a class="noticetext" href="msgs/msglist.php?cid='.$data[$i]['id'].'">', sprintf(_('Messages (%d)'), $newmsgcnt[$data[$i]['id']]), '</a>';
		}
		if ($shownewpostnote && isset($newpostcnt[$data[$i]['id']]) && $newpostcnt[$data[$i]['id']]>0) {
			echo ' <a class="noticetext" href="forums/newthreads.php?from=home&cid='.$data[$i]['id'].'">', sprintf(_('Posts (%d)'), $newpostcnt[$data[$i]['id']]), '</a>';
		}
		if ($type != 'teach' || $data[$i]['ownerid']!=$userid || $myrights<40) {
			echo '<div class="delx"><a href="#" onclick="return hidefromcourselist(this,'.$data[$i]['id'].',\''.$type.'\');" title="'._("Hide from course list").'" aria-label="'._("Hide from course list").'">x</a></div>';
		}
		echo '</li>';
	}
	if ($type=='teach' && $myrights>39 && count($data)==0) {
		echo '<li>', _('To add a course, click the button below'), '</li>';
	} else if ($type=='teach' && $myrights<13 && $myrights>10 && count($data)==0) {
		echo '<li>', _('Your instructor account has not been approved yet. Please be patient.'), '</li>';
	}
	echo '</ul>';
	if ($type=='take') {
		echo '<div class="center"><a class="abutton" href="forms.php?action=enroll">', _('Enroll in a New Class'), '</a></div>';
	} else if ($type=='teach' && $myrights>39) {
		echo '<div class="center"><a class="abutton" href="admin/forms.php?from=home&action=addcourse">', _('Add New Course'), '</a></div>';
	}
	
	echo '<div class="center">';
	echo '<a id="unhidelink'.$type.'" '.($hashiddencourses?'':'style="display:none"').' class="small" href="admin/unhidefromcourselist.php?type='.$type.'">View hidden courses</a>';
	echo '</div>';
	if ($type=='teach' && ($myrights>=75 || ($myspecialrights&4)==4)) {
		echo '<div class="center"><a class="abutton" href="admin/admin.php">', _('Admin Page'), '</a></div>';
	}
	echo '</div>';
	echo '</div>';
}

function printMessagesGadget() {
	global $page_newmessagelist, $page_coursenames;
	echo '<div role="complementary" aria-label="'._('New messages').'">';
	echo '<div class="block"><h3>', _('New messages'), '</h3></div>';
	echo '<div class="blockitems">';
	if (count($page_newmessagelist)==0) {
		echo '<p>', _('No new messages'), '</p>';
		echo '</div>';
		return;
	}
	echo '<table class="gb" id="newmsglist"><thead><tr><th>', _('Message'), '</th><th>', _('From'), '</th><th>', _('Course'), '</th><th>' ,_('Sent'), '</th></tr></thead>';
	echo '<tbody>';
	foreach ($page_newmessagelist as $line) {
		echo '<tr>';
		if (trim($line['title'])=='') {
			$line['title'] = '['._('No Subject').']';
		}
		$n = 0;
		while (strpos($line['title'],'Re: ')===0) {
			$line['title'] = substr($line['title'],4);
			$n++;
		}
		if ($n==1) {
			$line['title'] = 'Re: '.$line['title'];
		} else if ($n>1) {
			$line['title'] = "Re<sup>$n</sup>: ".$line['title'];
		}
		echo "<td><a href=\"msgs/viewmsg.php?cid={$line['courseid']}&type=new&msgid={$line['id']}\">";
		echo $line['title'];
		echo '</a></td>';
		echo '<td>'.$line['LastName'].', '.$line['FirstName'].'</td>';
		echo '<td>'.$page_coursenames[$line['courseid']].'</td>';
		echo '<td>'.tzdate("D n/j/y, g:i a",$line['senddate']).'</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<script type="text/javascript">initSortTable("newmsglist",Array("S","S","S","D"),false);</script>';
	echo '</div>';
	echo '</div>';

}
function printPostsGadget() {
	global $DBH,$page_newpostlist, $page_coursenames, $postthreads,$imasroot;
	echo '<div role="complementary" aria-label="'._('New forum posts').'">';
	echo '<div class="block">';
	//echo "<span class=\"floatright\"><a href=\"#\" onclick=\"GB_show('Forum Widget Settings','$imasroot/forms.php?action=forumwidgetsettings&greybox=true',800,'auto')\" title=\"Forum Widget Settings\"><img style=\"vertical-align:top\" src=\"$imasroot/img/gears.png\"/></a></span>";
	echo "<span class=\"floatright\"><a href=\"forms.php?action=forumwidgetsettings\"><img style=\"vertical-align:top\" src=\"$imasroot/img/gears.png\" alt=\"Settings\"/></a></span>";

	echo '<h3>', _('New forum posts'), '</h3></div>';
	echo '<div class="blockitems">';
	if (count($page_newpostlist)==0) {
		echo '<p>', _('No new posts'), '</p>';
		echo '</div>';
		return;
	}
	$threadlist = implode(',',$postthreads);
	$threaddata = array();
	//DB $query = "SELECT imas_forum_posts.*,imas_users.LastName,imas_users.FirstName FROM imas_forum_posts,imas_users ";
	//DB $query .= "WHERE imas_forum_posts.userid=imas_users.id AND imas_forum_posts.id IN ($threadlist)";
	//DB $result = mysql_query($query) or die("Query failed : $query " . mysql_error());
	//DB while ($tline = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$query = "SELECT imas_forum_posts.*,imas_users.LastName,imas_users.FirstName FROM imas_forum_posts,imas_users ";
	$query .= "WHERE imas_forum_posts.userid=imas_users.id AND imas_forum_posts.id IN ($threadlist)";  //int vals from DB - safe
	$stm = $DBH->query($query);
	while ($tline = $stm->fetch(PDO::FETCH_ASSOC)) {
		$threaddata[$tline['id']] = $tline;
	}

	echo '<table class="gb" id="newpostlist"><thead><tr><th>', _('Thread'), '</th><th>', _('Started By'), '</th><th>', _('Course'), '</th><th>', _('Last Post'), '</th></tr></thead>';
	echo '<tbody>';
	foreach ($page_newpostlist as $line) {
		echo '<tr>';
		$subject = $threaddata[$line['threadid']]['subject'];
		if (trim($subject)=='') {
			$subject = '['._('No Subject').']';
		}
		$n = 0;
		while (strpos($subject,'Re: ')===0) {
			$subject = substr($subject,4);
			$n++;
		}
		if ($n==1) {
			$subject = 'Re: '.$subject;
		} else if ($n>1) {
			$subject = "Re<sup>$n</sup>: ".$subject;
		}
		echo "<td><a href=\"forums/posts.php?page=-3&cid={$line['courseid']}&forum={$line['id']}&thread={$line['threadid']}\">";
		echo $subject;
		echo '</a></td>';
		if ($threaddata[$line['threadid']]['isanon']==1) {
			echo '<td>', _('Anonymous'), '</td>';
		} else {
			echo '<td>'.$threaddata[$line['threadid']]['LastName'].', '.$threaddata[$line['threadid']]['FirstName'].'</td>';
		}
		echo '<td>'.$page_coursenames[$line['courseid']].'</td>';
		echo '<td>'.tzdate("D n/j/y, g:i a",$line['lastposttime']).'</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<script type="text/javascript">initSortTable("newpostlist",Array("S","S","S","D"),false);</script>';

	echo '</div>';
	echo '</div>';
}


?>
