/*
 * 滑动栏 1.5 控件
 * MenuDynamic.css 1.5 基础样式
 * Author ： Cj
 * time ：2017-1-10 13:42:54
 */
/**
 * [getMenuConfig description]
 * Menus.create(a,b); a->配置参数 b->窗口开启id名
 * Menus.close();//盒子关闭方法
 * @param       {array} setting [配置参数]
 * @return      {array} MenuConfig [参数集]
 * 基础参数
 * width        {string} 宽度 和 滑出宽度 默认80%
 * height       {string} 高度 默认100%
 * zIndex       {string} 图层高度
 * position     {string} 滑动方位 默认右方right (left、right类型 高度100%固定,top、bottom类型 宽度100%固定)
 * bgColor      {string} 背景颜色
 * 是否开启遮罩层和锁
 * cover        {Booleans} 是否开启遮罩层 true 开 false 关
 * lock         {Booleans} 是否锁屏 true 开 false 关
 * 盒子头部参数
 * openHeader   {Booleans} 是否开启头部 true 开 false 关
 * textColor    {string} 字体颜色
 * headBg       {string} 头部背景 默认白色
 * headH        {string} 头部高度
 * headFsize    {string} 头部内容字体大小 默认50%
 * closeText    {string} 关闭按钮内容
 * title        {string} 头部标题
 * sureText     {string} 操作按钮内容
 * 操作按钮参数
 * sureId       {string} 添加的id名
 * sureAddClass {string} 添加的class名
 * sureClick    {string} 操作按钮绑定点击事件 lg：controll()
 * 盒子头部没有开启时参数
 * closeBtnId   {string} 关闭盒子的id对象
 * 盒子内容部分 (自动将 对象的html嵌入 至 盒子内容部分 )
 * ContObjType  {string} 内容模板对象类型 id / class 默认 空内容
 * ContObj      {string} 内容模板对象id/class 名 默认 MenuContent
 */
Menus = {'createMenu':'','close':''};
var getMenuConfig = function(a){
    var MenuConfig = {
        width  : '80%',
        height  : '100%',
        zIndex : '999999',
        position : 'right',
        bgColor : '#ffffff',
        cover : false,
        lock : false,
        openHeader : true,
        textColor : '#000000',
        headBg : '#ffffff',
        headH : '40px',
        headFsize : '80%', 
        closeText : '收起',
        title : '',
        sureText : '确认',
        sureId : '',
        sureAddClass : '',
        sureClick : '',
        closeBtnId : '',
        ContObjType : '',
        ContObj : 'MenuContent',
    };
    //配置参数
    for(var Conkey in MenuConfig){
        if( a.hasOwnProperty(Conkey) )
            MenuConfig[Conkey] = a[Conkey];
    }
    return MenuConfig;
}
//样式处理
var BoxStyle = function(c){
    var MenuBox = document.getElementById('navMenu');
    if(c.openHeader){
        var MenuBoxHead = MenuBox.getElementsByClassName('menu_header')[0];
        var HeadDiv = MenuBoxHead.getElementsByTagName('div');
        for(var k = 0;k < HeadDiv.length;k++){
            HeadDiv[k].style.color = c.textColor;
            HeadDiv[k].style.backgroundColor = c.headBg;
            HeadDiv[k].style.height = c.headH;
            HeadDiv[k].style.lineHeight = c.headH;
            HeadDiv[k].style.fontSize = c.headFsize;
        }
    }
}
//点击罩层收起
var MenuCloseFun = function(width,height,position){
    var MenuBoxhtml = document.getElementById('navMenu');
    var cover = document.getElementById('menuCover');
    cover.parentNode.removeChild(cover);
    var htmls = document.getElementsByTagName('html')[0];
    document.body.style.height = 'initial';
    document.body.style.overflow = 'auto';
    htmls.style.height = 'initial';
    htmls.style.overflow = 'auto';
    switch(position){
        case 'left' :
            MenuBoxhtml.style.left = '-'+width;
            break;
        case 'top' :
            MenuBoxhtml.style.top = '-'+height;
            break;
        case 'right' ://默认
            MenuBoxhtml.style.right = '-'+width;
            break;
        case 'bottom' :
            MenuBoxhtml.style.bottom = '-'+height;
            break;
    }
    MenuBoxhtml.className = 'closeMenu';
}

var createAnimationHtml = function(width,height,type){
    var openAnimation = '';
    var closeAnimation = '';
    switch(type){
        case 'left' :
            openAnimation = '@keyframes open{ from { left: -' + width + '} to { left: 0%} }';
            openAnimation += '@-webkit-keyframes open{ from { left: -' + width + '} to { left: 0%} }';
            openAnimation += '@-moz-keyframes open{ from { left: -' + width + '} to { left: 0%} }';
            openAnimation += '@-o-keyframes open{ from { left: -' + width + '} to { left: 0%} }';
            closeAnimation = '@keyframes close{ from { left: 0%} to { left: -' + width + '} }';
            closeAnimation += '@-webkit-keyframes close{ from { left: 0%} to { left: -' + width + '} }';
            closeAnimation += '@-moz-keyframes close{ from { left: 0%} to { left: -' + width + '} }';
            closeAnimation += '@-o-keyframes close{ from { left: 0%} to { left: -' + width + '} }';
            break;
        case 'top' :
            openAnimation = '@keyframes open{ from { top: -' + height + '} to { top: 0%} }';
            openAnimation += '@-webkit-keyframes open{ from { top: -' + height + '} to { top: 0%} }';
            openAnimation += '@-moz-keyframes open{ from { top: -' + height + '} to { top: 0%} }';
            openAnimation += '@-o-keyframes open{ from { top: -' + height + '} to { top: 0%} }';
            closeAnimation = '@keyframes close{ from { top: 0%} to { top: -' + height + '} }';
            closeAnimation += '@-webkit-keyframes close{ from { top: 0%} to { top: -' + height + '} }';
            closeAnimation += '@-moz-keyframes close{ from { top: 0%} to { top: -' + height + '} }';
            closeAnimation += '@-o-keyframes close{ from { top: 0%} to { top: -' + height + '} }';
            break;
        case 'right' ://默认
            openAnimation = '@keyframes open{ from { right: -' + width + '} to { right: 0%} }';
            openAnimation += '@-webkit-keyframes open{ from { right: -' + width + '} to { right: 0%} }';
            openAnimation += '@-moz-keyframes open{ from { right: -' + width + '} to { right: 0%} }';
            openAnimation += '@-o-keyframes open{ from { right: -' + width + '} to { right: 0%} }';
            closeAnimation = '@keyframes close{ from { right: 0%} to { right: -' + width + '} }';
            closeAnimation += '@-webkit-keyframes close{ from { right: 0%} to { right: -' + width + '} }';
            closeAnimation += '@-moz-keyframes close{ from { right: 0%} to { right: -' + width + '} }';
            closeAnimation += '@-o-keyframes close{ from { right: 0%} to { right: -' + width + '} }';
            break;
        case 'bottom' :
            openAnimation = '@keyframes open{ from { bottom: -' + height + '} to { bottom: 0%} }';
            openAnimation += '@-webkit-keyframes open{ from { bottom: -' + height + '} to { bottom: 0%} }';
            openAnimation += '@-moz-keyframes open{ from { bottom: -' + height + '} to { bottom: 0%} }';
            openAnimation += '@-o-keyframes open{ from { bottom: -' + height + '} to { bottom: 0%} }';
            closeAnimation = '@keyframes close{ from { bottom: 0%} to { bottom: -' + height + '} }';
            closeAnimation += '@-webkit-keyframes close{ from { bottom: 0%} to { bottom: -' + height + '} }';
            closeAnimation += '@-moz-keyframes close{ from { bottom: 0%} to { bottom: -' + height + '} }';
            closeAnimation += '@-o-keyframes close{ from { bottom: 0%} to { bottom: -' + height + '} }';
            break;
    }
    return openAnimation+closeAnimation;
}
//盒子创建
Menus.create = function(a,b){
    var c = getMenuConfig(a);
    var htmls = document.getElementsByTagName('html')[0];
    //盒子创建
    var MenuBoxhtml = document.createElement('div');
    MenuBoxhtml.id = 'navMenu';
    switch(c.position){
        case 'left' :
            MenuBoxhtml.style.width = c.width;
            MenuBoxhtml.style.height = '100%';
            MenuBoxhtml.style.top = '0';
            MenuBoxhtml.style.left = '-'+c.width;
            break;
        case 'top' :
            MenuBoxhtml.style.height = c.height;
            MenuBoxhtml.style.width = '100%';
            MenuBoxhtml.style.top = '-'+c.height;
            break;
        case 'right' ://默认
            MenuBoxhtml.style.width = c.width;
            MenuBoxhtml.style.height = '100%';
            MenuBoxhtml.style.top = '0';
            MenuBoxhtml.style.right = '-'+c.width;
            break;
        case 'bottom' :
            MenuBoxhtml.style.width = '100%';
            MenuBoxhtml.style.height = c.height;
            MenuBoxhtml.style.bottom = '-'+c.height;
            break;
        default:
            console.log('位置类型不正确');
            return false;
            break;
    }
    MenuBoxhtml.style.zIndex = c.zIndex;
    MenuBoxhtml.style.backgroundColor = c.bgColor;
    if(c.openHeader){
        var boxTitle = c.title;
        //盒子头部
        var MenuHeader = document.createElement('div');
        MenuHeader.className = 'menu_header';

        //头部标签内容
        //关闭按钮
        var closeBtn = document.createElement('div');
        closeBtn.className = 'close';
        closeBtn.innerHTML = c.closeText;
        MenuHeader.appendChild(closeBtn);

        //标题
        var MenuTitle = document.createElement('div');
        MenuTitle.className = 'title';
        MenuTitle.innerHTML = boxTitle;
        MenuHeader.appendChild(MenuTitle);

        //确认按钮
        var SureBtn = document.createElement('div');
        SureBtn.className = 'sure';
        if(c.sureId != '')
            SureBtn.id = c.sureId;

        if(c.sureAddClass != '')
            SureBtn.className = 'sure '+c.sureAddClass;

        if(c.sureClick != '')
            SureBtn.setAttribute('onclick',c.sureClick);

        SureBtn.innerHTML = c.sureText;
        MenuHeader.appendChild(SureBtn);

        // 插入
        MenuBoxhtml.appendChild(MenuHeader);
    }else{
        if(c.closeBtnId == '' && c.cover != true){
            console.log('未设置关闭按钮');
            return false;
        }
        var closeBtn = document.getElementById(c.closeBtnId);
    }
    //内容部分
    var MenuContHTML;
    if(c.ContObjType == 'id'){
        MenuContHTML = document.getElementById(c.ContObj);
    }else if(c.ContObjType == 'class'){
        MenuContHTML = document.getElementsByClassName(c.ContObj)[0];
    }else{
        MenuContHTML = document.createElement('div');
        MenuContHTML.style.textAlign = 'center';
        MenuContHTML.style.color = '#00000';
        MenuContHTML.innerHTML = '未设置内容';
    }

    if( MenuContHTML == undefined || MenuContHTML == null ){
        console.log('未放置对象');
    }else{
        MenuBoxhtml.appendChild(MenuContHTML);
        MenuContHTML.style.display = 'block';
    }

    // 插入
    document.body.appendChild(MenuBoxhtml);
    //样式处理
   BoxStyle(c);
    //控件部分
    var Menu = document.getElementById('navMenu');
    var MenuBox = document.getElementById(b);

    //动画插入
    var AnimationHtml = createAnimationHtml(c.width,c.height,c.position);
    var AnimationStyle = document.createElement('style');
    AnimationStyle.innerHTML = AnimationHtml;
    document.body.appendChild(AnimationStyle);

    //打开窗口
    MenuBox.onclick = function(){
        if(c.lock){
            document.body.style.height = '100%';
            document.body.style.overflow = 'hidden';
            htmls.style.height = '100%';
            htmls.style.overflow = 'hidden';
        }
        if(c.cover){
            var cover = document.createElement('div');
            cover.id = 'menuCover';
            cover.style.height = '100%';
            cover.style.width = '100%';
            cover.style.position = 'fixed';
            cover.style.top = '0';
            cover.style.left = '0';
            cover.style.backgroundColor = '#000000';
            cover.style.opacity = '.5';
            cover.style.zIndex = c.zIndex-1;
            cover.setAttribute('onclick','MenuCloseFun("' + c.width + '","' + c.height + '","' + c.position + '")');
            document.body.appendChild(cover);
        }
        switch(c.position){
            case 'left' :
                MenuBoxhtml.style.left = '0%';
                break;
            case 'top' :
                MenuBoxhtml.style.top = '0%';
                break;
            case 'right' ://默认
                MenuBoxhtml.style.right = '0%';
                break;
            case 'bottom' :
                MenuBoxhtml.style.bottom = '0%';
                break;
        }
        Menu.className = 'openMenu';
    }
    //关闭窗口
    closeBtn.onclick = function(){
        if(c.lock){
            document.body.style.height = 'initial';
            document.body.style.overflow = 'auto';
            htmls.style.height = 'initial';
            htmls.style.overflow = 'auto';
        }
        if(c.cover){
            var cover = document.getElementById('menuCover');
            cover.parentNode.removeChild(cover);
        }
        switch(c.position){
            case 'left' :
                MenuBoxhtml.style.left = '-'+c.width;
                break;
            case 'top' :
                MenuBoxhtml.style.top = '-'+c.height;
                break;
            case 'right' ://默认
                MenuBoxhtml.style.right = '-'+c.width;
                break;
            case 'bottom' :
                MenuBoxhtml.style.bottom = '-'+c.height;
                break;
        }
        Menu.className = 'closeMenu';
    };
    Menus.close = function(){
        if(c.lock){
            document.body.style.height = 'initial';
            document.body.style.overflow = 'auto';
            htmls.style.height = 'initial';
            htmls.style.overflow = 'auto';
        }
        if(c.cover){
            var cover = document.getElementById('menuCover');
            cover.parentNode.removeChild(cover);
        }
        switch(c.position){
            case 'left' :
                MenuBoxhtml.style.left = '-'+c.width;
                break;
            case 'top' :
                MenuBoxhtml.style.top = '-'+c.height;
                break;
            case 'right' ://默认
                MenuBoxhtml.style.right = '-'+c.width;
                break;
            case 'bottom' :
                MenuBoxhtml.style.bottom = '-'+c.height;
                break;
        }
        Menu.className = 'closeMenu';
    };
}