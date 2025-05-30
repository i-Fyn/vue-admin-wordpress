//(function () {
    tinymce.create('tinymce.plugins.editor_button', {
        init : (editor, url) => {
            editor.addButton('islide_hide', {
                text: '隐藏内容',
                // icon: 'preview',
                tooltip: '隐藏内容',
                // type: 'menubutton',
                // stateSelector: '.tinymce-hide',
                onclick : function() {
                    editor.insertContent('<p>[content_hide]</p><p><br data-mce-bogus="1"></p><p>[/content_hide]</p>');
                }
            });
            
            editor.addButton('islide_img', {
                text: '',
                icon: 'image',
                tooltip: '图片',
                onclick: function () {
                    createModal('imageUploadBox',{size:400,props:{
                        data:{
                            callback:(data,type) => {
                                let html = ''
                                if(data.length > 0){
                                  for (let i = 0; i < data.length; i++) {
                                    html += '<p><img src="'+data[i].url+'" /></p>';
                                  }
                                }
                
                                tinymce.activeEditor.insertContent(html);
                            }
                        }
                        
                    }})
                },
                // onTouchEnd: function () {
                //     writeHead.showPostThumb = false
                // }
            })
            
            function toggleFormat(fmt) {
                editor.formatter.toggle(fmt)
                editor.nodeChanged()
            };
    
            editor.addButton('islide_h2', {
                title: 'Heading 2',
                icon: 'c-svg heading2',
                stateSelector: 'h2',
                onclick: function () {
                    toggleFormat('h2')
                },
                onPostRender: function () {
                    document.querySelector('.mce-i-c-svg.heading2').innerHTML = '<svg viewBox="0 0 1024 1024" style="width: 21px;height: 21px;fill: currentColor;"><path d="M143.616 219.648v228.864h278.016V219.648h89.856V768H421.632v-242.688H143.616V768H53.76V219.648h89.856z m660.48-10.752c52.992 0 96.768 15.36 131.328 46.08 33.792 30.72 50.688 69.888 50.688 119.04 0 47.616-18.432 90.624-53.76 129.792-16.554667 17.706667-43.093333 39.082667-78.933333 64.426667l-22.613334 15.701333-12.117333 8.192c-52.309333 34.389333-85.248 64.810667-99.413333 91.178667l-2.730667 5.589333h270.336V768h-382.464c0-56.064 17.664-104.448 54.528-145.92 8.746667-10.069333 21.76-22.186667 38.912-36.352l15.786667-12.586667c5.589333-4.352 11.52-8.874667 17.834666-13.568l19.84-14.506666 21.888-15.488 11.690667-8.106667c35.328-24.576 59.904-45.312 75.264-61.44 23.808-26.88 36.096-56.064 36.096-86.784 0-29.952-8.448-52.224-23.808-66.816-16.128-14.592-39.936-21.504-71.424-21.504-33.792 0-59.136 11.52-76.032 34.56-15.36 19.541333-24.362667 48.64-27.050667 86.058667l-0.597333 11.477333h-89.856c0.768-61.44 18.432-110.592 53.76-148.224 36.096-39.936 83.712-59.904 142.848-59.904z"></path></svg>'
                }
            });
    
            editor.addButton('islide_h3', {
                title: 'Heading 3',
                icon: 'c-svg heading3',
                stateSelector: 'h3',
                onclick: function () {
                    toggleFormat('h3')
                },
                onPostRender: function () {
                    document.querySelector('.mce-i-c-svg.heading3').innerHTML = '<svg viewBox="0 0 1024 1024" style="width: 21px;height: 21px;fill: currentColor;"><path d="M801.024 208.896c55.296 0 100.608 13.056 134.4 39.936 33.024 26.88 49.92 63.744 49.92 111.36 0 59.904-30.72 99.84-91.392 119.808 32.256 9.984 57.6 24.576 74.496 44.544 18.432 20.736 27.648 47.616 27.648 79.872 0 50.688-17.664 92.16-52.992 124.416-36.864 33.024-85.248 49.92-145.152 49.92-56.832 0-102.912-14.592-137.472-43.776-38.4-32.256-59.904-79.872-64.512-141.312h91.392c1.536 35.328 12.288 62.976 33.792 82.176 19.2 17.664 44.544 26.88 76.032 26.88 34.56 0 62.208-9.984 82.176-29.184 17.664-17.664 26.88-39.168 26.88-65.28 0-31.488-9.984-54.528-28.416-69.12-18.432-15.36-45.312-22.272-80.64-22.272h-38.4V449.28h38.4c32.256 0 56.832-6.912 73.728-20.736 16.128-13.824 24.576-34.56 24.576-61.44 0-26.88-7.68-46.848-22.272-60.672-16.128-13.824-39.936-20.736-71.424-20.736-32.256 0-56.832 7.68-74.496 23.808-18.432 16.128-29.184 40.704-32.256 73.728h-88.32c4.608-55.296 24.576-98.304 61.44-129.024 34.56-30.72 79.104-45.312 132.864-45.312z m-657.408 10.752v228.864h278.016V219.648h89.856V768H421.632v-242.688H143.616V768H53.76V219.648h89.856z"></path></svg>'
                }
            });
            
            // editor.addButton('islide_list', {
            //     text: '',
            //     icon: 'bullist',
            //     tooltip: '列表',
            //     type: 'menubutton',
            //     stateSelector: '.tinymce-hide',
            //     menu: [{
            //         icon: 'bullist',
            //         text: '',
            //         tooltip: '列表',
            //         onclick: function () {
            //             toggleFormat('bullist')
            //         }
            //     }, {
            //         icon: 'numlist',
            //         text: '',
            //         tooltip: '列表',
            //         onclick: function () {
            //             toggleFormat('numlist')
            //         }
            //     }]
            // });
        }
    })
    
    tinymce.PluginManager.add('editor_button', tinymce.plugins.editor_button );
    
    // 将Label类定义放在外部  
    class Label {  
        constructor(editor, placeholderText) {  
            this.editor = editor;  
            this.placeholderText = placeholderText;  
            this.init();  
        }  
      
        init() {  
            let placeholderAttrs = this.editor.settings.placeholder_attrs || {
                style: {
                    position: 'absolute',
                    top: 9,
                    left: 0,
                    color: 'rgba(136, 136, 136, 0.6)',
                    padding: '10px',
                    width: '100%',
                    'white-space': 'pre-wrap',
                    'font-size': '15px',
                    'box-sizing': 'border-box'
                }
            };  
            let contentAreaContainer = this.editor.getContentAreaContainer();  
      
            // Create label element  
            this.el = tinymce.DOM.add(contentAreaContainer,'label', placeholderAttrs, this.placeholderText);  
      
            // 可能需要额外的样式来定位占位符  
            this.updatePosition();  
        }  
      
        updatePosition() {  
            // 更新占位符的位置逻辑  
            // 这里可以根据需要调整top值  
            // let top = this.editor.getContainer().querySelector('.mce-top-part').clientHeight + 11;  
            // tinymce.DOM.setStyle(this.el, 'top', top + 'px');  
        }  
      
        hide() {  
            tinymce.DOM.setStyle(this.el, 'display', 'none');  
        }  
      
        show() {  
            tinymce.DOM.setStyle(this.el, 'display', 'block');  
            this.updatePosition();  
        }  
    }  
      
    tinymce.PluginManager.add('placeholder', function(editor, url) {  
        let label; // 存储Label实例  
        let placeholderText = editor.getElement().getAttribute('placeholder') || editor.settings.placeholder || '请开始你的表演...';  
      
        editor.on('init', function() {  
            label = new Label(editor, placeholderText); // 创建Label实例  
            onBlur()
            // 绑定事件  
            tinymce.DOM.bind(label.el, 'click', () => editor.focus());  
            editor.on('focus', () => label.hide());  
            editor.on('blur', onBlur);  
            editor.on('change', onBlur);  
            editor.on('setContent', onBlur);  
            editor.on('keydown', () => label.hide());  
      
            // ... 其他初始化代码 ...  
        });  
      
        function onBlur() {  
            // 更可靠地检查编辑器是否为空  
            let content = editor.getContent({ format: 'text' }).trim(); 
            
            if (content == '' || content === '<p><br data-mce-bogus="1"></p>') {  
                label.show();  
            } else {  
                label.hide();  
            }  
        }
    });  
    
   // 设置 siteMain 的 margin-top 样式  
    function setSiteMainMarginTop() {  
        var siteMain = document.querySelector('.write-page .site-main');  
        if (siteMain) {  
            var hidetb = window.getUserSetting('hidetb');  
            if (hidetb == '1') {  
                siteMain.style.marginTop = '38px';  
            } else {  
                siteMain.style.marginTop = '';  
            }  
        }  
    }  
      
    // 当 DOMContentLoaded 事件触发时（页面加载完成）  
    addLoadEvent(() => {  
        // 立即根据 cookie 设置样式  
        setSiteMainMarginTop();  
        // 获取 TinyMCE 编辑器实例  
        var editor = tinymce.get('post_content'); // 使用你的选择器替换 'textarea'  
      
        // 如果编辑器存在，则添加监听器  
        if (editor) {  
            editor.on('ExecCommand', (e) => {  
                // 检查执行的命令  
                if (e.command === 'WP_Adv') {  
                    // 当执行特定命令时，再次设置样式  
                    setSiteMainMarginTop();  
                }  
            });  
        }  
    });
//})();