<?php namespace islide\Menu;


class Main{
    public function init(){
        //加载模板
        $this->load_menus();
    }
    
    public function load_menus(){
        $video = new Video();
        $video->init();
        $circle = new Circle();
        $circle->init();
        $shop = new Shop();
        $shop->init();
        $notice = new Notice();
        $notice->init();
        $book = new Book();
        $book->init();
    }
    
}