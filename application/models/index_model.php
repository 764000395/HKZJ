<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Index_model extends CI_model {
	//关联user表和article表 获取文章列表+用户头像、昵称等信息
	public function get_user_article_list($where_arr, $order_str, $offset, $per_page = 10) {
		$get_info = 'article_id, article.user_id, type, title, content, article.create_time, praise, read, is_top, nickname, headimgurl, vip,solve';
        //var_dump($where_arr);
		if($where_arr == '论坛'){ // 论坛首页展示所有文章
		    $this->db->where_in('type', array('卡友生活', '卡友经验', '自由贸易', '灌水区'));
        }else if($where_arr == '论坛+求助'){
            $this->db->where_in('type', array('卡友生活', '卡友经验', '自由贸易', '灌水区', '卡友求助'));
        } else{
		    $this->db->where($where_arr);
        }
		$status = $this->db->select($get_info)->order_by($order_str)->limit($per_page, $offset)->join('user', 'article.user_id = user.user_id')->get('article')->result_array();
		//echo $this->db->last_query();
		return $status;
	}
	//获取文章列表
    public function get_article_list($where_arr, $offset, $per_page = 10){
        $status = $this->db->select('*')->order_by('create_time DESC')->limit($per_page, $offset)->get_where('article', $where_arr)->result_array();
        return $status;
    }
    //获取搜索的信息
	public function get_help_search($search, $offset, $per_page = 10) {
		$search = addslashes($search);
		$offset = addslashes($offset);
		$sql = 'SELECT * FROM article WHERE type="卡友求助" AND ( title LIKE "%' . $search . '%" ESCAPE "!" OR content LIKE "%' . $search . '%" ESCAPE "!") ORDER BY create_time DESC LIMIT ' . $offset . ' ,' . $per_page;
		$status = $this->db->query($sql)->result_array();
		return $status;
	}
	//获取共有多少条搜索到的信息
	public function get_help_search_count($search) {
		$search = addslashes($search);
		$sql = 'SELECT COUNT(*) AS total_rows FROM article WHERE type="卡友求助" AND ( title LIKE "%' . $search . '%" ESCAPE "!" OR content LIKE "%' . $search . '%" ESCAPE "!")';
		$status = $this->db->query($sql)->result_array();
		return $status;
	}
	//获取文章信息
	public function get_article($where_arr) {
		$status = $this->db->get_where('article', $where_arr)->result_array();
		return $status;
	}
	//获取用户信息
	public function get_user($where_arr) {
		$get_info = 'user_id, nickname, headimgurl, phone, signature, sex, province, city, vip, status, openid';
		$status = $this->db->select($get_info)->get_where('user', $where_arr)->result_array();
		return $status;
	}
	//获取评论信息
	public function get_user_comment_list($where_arr, $order_str, $offset, $per_page = 10) {
		$get_info = 'comment_id, comment.user_id, content, comment.create_time, praise, pid, nickname, headimgurl, vip';
		$status = $this->db->select($get_info)->order_by($order_str)->limit($per_page, $offset)->join('user', 'user.user_id = comment.user_id')->get_where('comment', $where_arr)->result_array();
		return $status;
	}
	//获取论坛消息
    public function get_message_list($where_arr, $offset, $per_page=10){
        $get_info = 'comment_id, content, comment.user_id, article_id, comment.create_time, pid, nickname, headimgurl';
        $status = $this->db->select($get_info)->order_by('comment.create_time DESC')->join('user', 'comment.user_id = user.user_id')->get_where('comment', $where_arr, $per_page, $offset)->result_array();
        return $status;
    }
	/*
		*处理文章列表数据
		*获取
	*/
	public function format_data($data, $get_comment=true) {
		//检查有没有信息 如果没有返回空数组 不予遍历
		if (empty($data)) {
			return array();
		}
		$pattern_img = "/<(img|IMG)(.*?)(\/>|><\/img>|>)/";
		$pattern_src = '/(src|SRC)=(\'|\")(.*?)(\'|\")/';
		$status = array();
		foreach ($data as $datas) {
			$datas['url'] = array();
			//先匹配img标签
			if (preg_match_all($pattern_img, $datas['content'], $matchs)) {
				//再匹配img标签中图片src地址

				//匹配第一张图片地址
				if (preg_match_all($pattern_src, $matchs[0][0], $src)) {
					$datas['url'][0] = $src[3][0];
				}
				//如果有第二张图 继续匹配第二张图片地址
				if (isset($matchs[0][1]) && !empty($matchs[0][1]) && preg_match_all($pattern_src, $matchs[0][1], $src)) {
					$datas['url'][1] = $src[3][0];
				}
				//如果有三张图  继续匹配第三张图片地址
				if (isset($matchs[0][2]) && !empty($matchs[0][2]) && preg_match_all($pattern_src, $matchs[0][2], $src)) {
					$datas['url'][2] = $src[3][0];
				}
			}
			//获取文章类型标签类名

			//格式化时间， 几分钟前形式
			$datas['create_time'] = formatTime($datas['create_time']);
			//获取每一篇文章的评论量
            if($get_comment){
                $comment_total = $this->db->where(array('article_id' => $datas['article_id']))->count_all_results('comment');
                $datas['comment_total'] = $comment_total;
                $datas['type_class'] = $this->get_type_class($datas['type']);
            }

			//去掉html标签
			$datas['content'] = preg_replace('/<\/?\s*\w+.*?>/', '', $datas['content']);
			$datas['content'] = strlen($datas['content']) > 60 ? mb_substr($datas['content'], 0, 60) . '...' : $datas['content'];
			$status[] = $datas;
		}
		return $status;
	}

	//获取评论的回复
	public function get_reply_comment($comment) {
		if (empty($comment)) {
			return $comment;
		}

		$status = array();
		foreach ($comment as $c) {
			$c['reply'] = $this->get_user_comment_list(array('pid' => $c['comment_id']), 'create_time DESC', 0, 10);
			$status[] = $c;
		}
		return $status;
	}

	//获取文章类型标签的类名
	private function get_type_class($type) {
		$type_class = '';
		switch ($type) {
		case '卡友生活':
			$type_class = 'live_orange';
			break;
		case '卡友经验':
			$type_class = 'exper_green';
			break;
		case '卡友求助':
			$type_class = 'help_red';
			break;
		case '自由贸易':
			$type_class = 'free_purprl';
			break;
		case '灌水区':
			$type_class = 'chat_bule';
			break;
		default:
			# code...
			break;
		}
		return $type_class;
	}

    /***************************** 论坛部分结束 ***************************/

    /*************************  司机群部分Begin ***************************/

    //获取司机群列表
    public function get_flock_list($where_arr, $offset, $per_page = 10){
        $status = $this->db->order_by('create_time DESC')->get_where('flock', $where_arr, $per_page, $offset)->result_array();
        return $status;
    }

    //搜索司机群列表
    public function get_search_flock_list($keywords, $offset, $per_page=10){
        $keywords = addslashes($keywords);
        $this->db->like('title', $keywords)->or_like('province', $keywords)->or_like('city', $keywords)->or_like('county', $keywords);
        $status = $this->db->select('*')->get('flock', $per_page, $offset)->result_array();
        //echo $this->db->last_query();
        //var_dump($status);
        return $status;
    }

    //获取司机群
    public function get_flock($where_arr){
        $status = $this->db->select('*')->get_where('flock', $where_arr)->result_array();
        return $status;
    }
}
