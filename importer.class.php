<?php
set_time_limit(0);
require __DIR__.'/vendor/autoload.php';

/**
 * Importer Class
 * 
 * This class will extract zip and import all content to wordpress.
 **/

class Importer {
	private $zip;
	private $imported_images=[];
	private $current_date=null;
	private $total_inserted_post=0;
	private $total_inserted_image=0;
	private $total_failed_image=0;
	private $total_failed_post=0;
	private $errors=[];

	function doImport( $zip_file ){
		if(!$zip_file) throw new \Exception("You not selecting a file!");

		$this->zip = new \ZipArchive;
		$open = $this->zip->open($zip_file);

		if($open !== TRUE) throw new \Exception("Failed to open zip file!");

		for ($i=0; $i < $this->zip->numFiles; $i++) { 
			$filename = $this->zip->getNameIndex($i);

			if(!preg_match('#^content/.*?.md$$#i', $filename)) continue;

			$this->processMarkdown($filename);

		}

		@unlink($zip_file);

		return [
			'post_inserted' => $this->total_inserted_post,
			'post_failed'	=> $this->total_failed_post,
			'image_inserted'=> $this->total_inserted_image,
			'image_failed'	=> $this->total_failed_image,
			'errors'		=> $this->errors
		];
		
	}

	/**
	 * Proccess markdown file
	 **/
	function processMarkdown( $path ){
		$content = $this->zip->getFromName($path);

		$parser = new Mni\FrontYAML\Parser;
		$document = $parser->parse($content, false);

		$yaml = $document->getYAML();
		$this->current_date = $yaml['date'];

		$content = preg_replace_callback('#!\[(.*?)\]\((.*?)\s*("(?:.*[^"])")?\s*\)#s', [$this, 'processImage'], $content);

		$document = $parser->parse($content);
		$yaml = $document->getYAML();
		$html = $document->getContent();

		$cats = is_array($yaml['categories'])?$yaml['categories']:[$yaml['categories']];
		$cats_id = [];
		foreach($cats as $cat){
			$cat_slug = sanitize_title($cat);
			wp_insert_term($cat, 'category', [
			    'slug'	=>	$cat_slug,
			]);
			$term_ID = 0;
			if($term = get_term_by( 'slug', $cat_slug, 'category' )){
				$term_ID = $term->term_id;
			}
			$cats_id[] = $term_ID;
		}

		$post_id = wp_insert_post([
			'post_title'	=> wp_strip_all_tags( $yaml['title'] ),
			'post_content'	=> $html,
			'post_date'		=> date('Y-m-d H:i:s',$yaml['date']),
			'post_status'	=> @$yaml['draft']?'draft':'publish',
			'post_excerpt'	=> @$yaml['description']?$yaml['description']:"",
			'post_category'	=> @$cats_id,
			'tags_input'	=> @$yaml['tags'],
			'post_name'		=> basename($path, '.md')
		], true);


		if(!is_wp_error($post_id)){
			$thumbnail=false;

			if(@$yaml['featured_image']) $thumbnail=$yaml['featured_image'];
			if(@$yaml['image']) $thumbnail=$yaml['image'];
			if(is_array($thumbnail)){
				if(@$thumbnail['src']){
					$thumbnail=$thumbnail['src'];
				}else{
					$thumbnail=false;
				}
			}

			if($thumbnail){
				$media_id = $this->imageToServer( $thumbnail, TRUE );
				if($media_id){
					set_post_thumbnail( $post_id, $media_id );
				}
			}
			$this->total_inserted_post++;
		}else{
			$this->errors[]=$post_id->get_error_message();
			$this->total_failed_post++;

		}
	}
	/**
	 * Handle image in markdown
	 **/
	function processImage( $match ){
		$alt = @$match[1];
		$url = @trim($match[2]);
		$title = @$match[3];

		$parsed = parse_url($url);
		if (!empty($parsed['scheme'])) {
		    return '!['.$alt.']('.$url.($title?' '.$title:'').')';
		}

		if(isset($this->imported_images[$url])){
			return '!['.$alt.']('.$this->imported_images[$url].($title?' '.$title:'').')';
		}

		$local_url = $this->imageToServer( $url );
		return '!['.$alt.']('.$local_url.($title?' '.$title:'').')';
	}
	/**
	 * Handle image upload from zip file
	 **/
	function imageToServer( $path, $return_id = false ){
		$full_path = 'static/'.trim($path,'/');

		$image_content = $this->zip->getFromName($full_path);

		if(!$image_content){
			$full_path = 'static/'.trim(urldecode($path),'/');
			$image_content = $this->zip->getFromName($full_path);
			if(!$image_content){
				$total_failed_image++;
				return FALSE;
			}
		}

		$basename = urldecode(basename($full_path));

		$wp_upload_dir = wp_upload_dir(date('Y/m',$this->current_date), true);

		$upload_dir = $wp_upload_dir['path'].'/';

		$new_path = $upload_dir.$basename;

		$finfo = new \finfo(FILEINFO_MIME);
		$mime = $finfo->buffer($image_content);

		$i=1;
		while(file_exists($new_path)){
			$i++;
			$new_path = $upload_dir.$i.'_'.urldecode(basename($full_path));
			$basename = $i.'_'.urldecode(basename($full_path));
		}

		if(file_put_contents($new_path, $image_content)){

			$image_id = wp_insert_attachment([
				'guid'				=>	$new_path,
				'post_mime_type'	=>	$mime,
				'post_title'		=>	$basename,
				'post_content'		=>	'',
				'post_status'		=>	'inherit'
			], $new_path);

			wp_update_attachment_metadata( $image_id, wp_generate_attachment_metadata( $image_id, $new_path ));
			$this->total_inserted_image++;
			$imported_images[$path] = $wp_upload_dir['url'].'/'.$basename;
			if($return_id) return $image_id;
			return $wp_upload_dir['url'].'/'.$basename;

		}else{
			throw new \Exception("Cannot insert image!");
		}

	}
}
?>