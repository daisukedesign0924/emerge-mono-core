(function($){
'use strict';
function msg($el,text,ok){$el.removeClass('is-ok is-err').addClass(ok?'is-ok':'is-err').text(text||'')}
function post(action,data){data=data||{};data.action=action;data.nonce=emcoreAdmin.nonce;return $.post(emcoreAdmin.ajaxUrl,data)}
function emcoreDialog(options){
  options=$.extend({kicker:'EMERGE MONO / CONFIRM',title:'確認',message:'',confirmLabel:'確認',cancelLabel:'キャンセル',showCancel:true,danger:false,input:false,inputLabel:'',inputValue:'',placeholder:'',required:false,expected:''},options||{});
  return new Promise(function(resolve){
    var modal=document.createElement('div');modal.className='emcore-modal emcore-system-modal';modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');
    var $modal=$(modal),$card=$('<div class="emcore-modal-card"></div>'),$actions=$('<div class="emcore-modal-actions"></div>'),$confirm=$('<button type="button" class="emcore-btn"></button>').text(options.confirmLabel),$cancel=$('<button type="button" class="emcore-btn"></button>').text(options.cancelLabel);
    if(options.danger)$confirm.addClass('emcore-btn-danger');else $confirm.addClass('emcore-btn-primary');
    $card.append($('<div class="emcore-modal-kicker"></div>').text(options.kicker),$('<h3></h3>').text(options.title));
    if(options.message)$card.append($('<p class="emcore-modal-lead"></p>').text(options.message));
    var $input=null,$error=$('<div class="emcore-dialog-error emcore-msg" role="status"></div>');
    if(options.input){$input=$('<input type="text" class="emcore-input" autocomplete="off">').val(options.inputValue).attr('placeholder',options.placeholder);$card.append($('<label></label>').text(options.inputLabel||'入力'),$input,$error)}
    if(options.showCancel)$actions.append($cancel);$actions.append($confirm);$card.append($actions);$modal.append($card);$('body').append($modal);
    function close(value){$modal.remove();resolve(value)}
    function submit(){var value=$input?String($input.val()||'').trim():true;if(options.required&&!value){$input.addClass('is-error').trigger('focus');msg($error,'入力してください。',false);return}if(options.expected&&value!==options.expected){$input.addClass('is-error').trigger('focus');msg($error,options.expected+' と正確に入力してください。',false);return}close(value)}
    $cancel.on('click',function(){close(false)});$confirm.on('click',submit);
    setTimeout(function(){if($input)$input.trigger('focus').select();else $confirm.trigger('focus')},30);
  });
}
function emcoreAlert(message,title){return emcoreDialog({kicker:'EMERGE MONO / NOTICE',title:title||'お知らせ',message:message,confirmLabel:'閉じる',showCancel:false}).then(function(v){return v})}
function emcoreConfirm(message,options){return emcoreDialog($.extend({title:'確認してください',message:message,confirmLabel:'続ける'},options||{}))}
function emcoreConfirmDelete(target,message){
  return emcoreDialog({kicker:'EMERGE MONO / DELETE',title:'「'+target+'」を削除',message:message||'この操作を実行するには、下の入力欄に DELETE と入力してください。',input:true,inputLabel:'確認用テキスト',placeholder:'DELETE',required:true,expected:'DELETE',confirmLabel:'削除する',danger:true});
}
function emcoreReadHtmlFile(file, cb){
  if(!file){cb(null,'No file');return}
  var name=(file.name||'').toLowerCase();
  if(!/\.html?$/.test(name) && file.type && file.type.indexOf('html')===-1){
    cb(null,'HTMLファイルを選んでください');return
  }
  if(file.size > 2*1024*1024){cb(null,'2MBまでです');return}
  var reader=new FileReader();
  reader.onload=function(e){cb(e.target.result,null)};
  reader.onerror=function(){cb(null,'読み込みに失敗しました')};
  reader.readAsText(file);
}
function emcoreReadSpecHints(html){
  try{var doc=new DOMParser().parseFromString(html,'text/html'),body=doc.querySelector('[data-em-part="body"]');if(!body)return;var type=body.getAttribute('data-em-template-type');if(type==='archive'||type==='single'||type==='page'||type==='front-page')$('#emcore-tpl-type').val(type==='front-page'?'page':type);var label=body.getAttribute('data-em-label');if(label&&!$('#emcore-tpl-name').val())$('#emcore-tpl-name').val(label)}catch(e){}
}

$('#emcore-copy-spec').on('click',function(){
  var text=$('#emcore-spec-source').val()||'', $msg=$('#emcore-spec-msg');
  if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(text).then(function(){msg($msg,'コピーしました',true)},function(){msg($msg,'コピーできませんでした',false)})}
  else{var el=document.getElementById('emcore-spec-source');el.focus();el.select();try{document.execCommand('copy');msg($msg,'コピーしました',true)}catch(e){msg($msg,'コピーできませんでした',false)}}
});
$('#emcore-rule-file').on('change',function(){
  var f=this.files&&this.files[0],$name=$('#emcore-rule-file-name'),$out=$('#emcore-rule-result').empty();
  if(!f){msg($name,'',true);return}
  emcoreReadHtmlFile(f,function(html,err){
    if(err){msg($name,err,false);return}
    $('#emcore-rule-html').val(html);
    msg($name,'読み込みました: '+f.name,true);
  });
});
$('#emcore-check-rules').on('click',function(){
  var html=$('#emcore-rule-html').val(),$out=$('#emcore-rule-result').empty();
  if(!html.trim()){$out.html('<p class="emcore-msg is-err">HTMLを入力してください。</p>');return}
  post('emcore_diagnose_template',{html:html}).done(function(r){
    if(!r.success){$out.html('<p class="emcore-msg is-err">確認できませんでした。</p>');return}
    var c=r.data.compliance||{},status=c.status==='conformant'?'準拠':(c.status==='partial'?'一部準拠':'非準拠');
    var $head=$('<div class="emcore-compliance-score"></div>');$head.append($('<strong></strong>').text((c.score||0)+' / 100'));$head.append($('<span></span>').text(status));$out.append($head);
    var $list=$('<ul class="emcore-compliance-list"></ul>');(c.checks||[]).forEach(function(x){var $li=$('<li></li>').addClass(x.ok?'is-ok':'is-ng');$li.append($('<strong></strong>').text((x.ok?'✓ ':'× ')+x.label));if(!x.ok&&x.message)$li.append($('<span></span>').text(x.message));$list.append($li)});$out.append($list);
    var diagnostics=r.data.diagnostics||[],$diagnostics=$('<div class="emcore-diagnostics"></div>');
    $diagnostics.append($('<h4></h4>').text('HTML診断 '+diagnostics.length+'件'));
    if(!diagnostics.length){$diagnostics.append($('<p class="emcore-msg is-ok"></p>').text('注意事項はありません。'))}
    else{var $diagnosticList=$('<ul class="emcore-diagnostic-list"></ul>');diagnostics.forEach(function(x){var label=x.message||x.label||String(x);$diagnosticList.append($('<li></li>').addClass('is-'+(x.level||'warning')).text(label))});$diagnostics.append($diagnosticList)}
    $out.append($diagnostics);
  });
});

$('#emcore-new-tpl').on('click',function(){$('#emcore-new-tpl-form').slideToggle(150)});
$('#emcore-tpl-cancel').on('click',function(){$('#emcore-new-tpl-form').slideUp(150)});
$('#emcore-tpl-file').on('change',function(){
  var f=this.files&&this.files[0]; if(!f)return;
  var $msg=$('#emcore-tpl-msg');
  emcoreReadHtmlFile(f,function(html,err){
    if(err){msg($msg,err,false);return}
    $('#emcore-tpl-html').val(html);
	emcoreReadSpecHints(html);
    if(!$('#emcore-tpl-name').val()){
      $('#emcore-tpl-name').val(f.name.replace(/\.html?$/i,''));
    }
    msg($msg,'読み込みました: '+f.name,true);
  });
});
$('#emcore-edit-file').on('change',function(){
  var f=this.files&&this.files[0]; if(!f)return;
  var $msg=$('#emcore-html-msg');
  emcoreReadHtmlFile(f,function(html,err){
    if(err){msg($msg,err,false);return}
    $('#emcore-edit-html').val(html);
    msg($msg,'読み込みました。HTMLを更新を押してください',true);
  });
});
$('#emcore-tpl-save').on('click',function(){
  var $m=$('#emcore-tpl-msg'),html=$('#emcore-tpl-html').val();
  post('emcore_save_template',{name:$('#emcore-tpl-name').val(),type:$('#emcore-tpl-type').val(),html:html}).done(function(r){
    if(r.success){msg($m,r.data.message,true);setTimeout(function(){location.reload()},600)}else msg($m,(r.data&&r.data.message)||emcoreAdmin.i18n.error,false);
  });
});
$(document).on('click','.emcore-del-tpl',async function(){
  var id=$(this).data('id');
  if(!await emcoreConfirmDelete('テンプレート'))return;
  var r=await post('emcore_delete_template',{id:id,confirm_text:'DELETE'});if(r.success){location.reload();return}
  var d=r.data||{};if(!d.requires_confirmation){await emcoreAlert(d.message||emcoreAdmin.i18n.error,'削除できません');return}
  var lines=[d.message||'使用中です。'];if(d.pages&&d.pages.length)lines.push('ゴミ箱へ移動する固定ページ:\n・'+d.pages.join('\n・'));if(d.cpts&&d.cpts.length)lines.push('テンプレート割り当てを解除する投稿タイプ:\n・'+d.cpts.join('\n・'));lines.push('投稿タイプ内の記事は削除されません。固定ページはゴミ箱から復元できます。');
  if(!await emcoreConfirm(lines.join('\n\n'),{kicker:'EMERGE MONO / FORCE DELETE',title:'使用中の関連付けを解除しますか？',confirmLabel:'解除して削除',danger:true}))return;
  var done=await post('emcore_delete_template',{id:id,force:'1',confirm_text:'DELETE'});if(done.success)location.reload();else await emcoreAlert((done.data&&done.data.message)||emcoreAdmin.i18n.error,'削除できません');
});
$(document).on('click','.emcore-restore-tpl',async function(){
  if(!await emcoreConfirm('現在の状態を履歴へ保存してから、選択した版へ戻します。',{kicker:'EMERGE MONO / HISTORY',title:'この状態に戻しますか？',confirmLabel:'この版に戻す'}))return;
  post('emcore_restore_template',{id:$(this).data('id'),index:$(this).data('index')}).done(function(r){
    if(r.success)location.reload();else emcoreAlert((r.data&&r.data.message)||emcoreAdmin.i18n.error,'復元できません');
  });
});
function emcorePageSlug(value){return String(value||'').trim().toLowerCase().replace(/[^a-z0-9_-]+/g,'-').replace(/^-+|-+$/g,'')}
function emcorePublicUrl(slug){
  var base=String((window.emcoreAdmin&&emcoreAdmin.homeUrl)||location.origin+'/').replace(/\/+$/,'');
  var path=String(slug||'slug').replace(/^\/+|\/+$/g,'');
  return base+'/'+path+'/';
}
function emcoreOpenPageCreateModal($source){
  var isArchive=String($source.data('type'))==='archive',modal=document.createElement('div');
  modal.className='emcore-modal emcore-page-create-modal';modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');modal.setAttribute('aria-labelledby','emcore-page-create-title');
  modal.innerHTML='<div class="emcore-modal-card">'+
    '<div class="emcore-modal-kicker">NEW PAGE / EMERGE MONO</div>'+
    '<h3 id="emcore-page-create-title">'+(isArchive?'一覧ページを作成':'固定ページを作成')+'</h3>'+
    '<p class="emcore-modal-lead">テンプレートから公開用の固定ページを作成します。タイトルとURLを確認してください。</p>'+
    '<label for="emcore-page-title">ページタイトル</label><input type="text" id="emcore-page-title" class="emcore-input" autocomplete="off">'+
    '<label for="emcore-page-slug">URLスラッグ</label><div class="emcore-slug-field"><span>/</span><input type="text" id="emcore-page-slug" class="emcore-input" inputmode="url" autocomplete="off" spellcheck="false"><span>/</span></div>'+
    '<p class="emcore-help">半角英数字・ハイフン・アンダーバーが使用できます。</p>'+
    '<div class="emcore-url-preview"><span>公開URL</span><code></code></div>'+
    '<div class="emcore-page-create-message emcore-msg" role="status"></div>'+
    '<div class="emcore-modal-actions"><button type="button" class="emcore-btn" data-action="cancel">キャンセル</button><button type="button" class="emcore-btn emcore-btn-primary" data-action="create">ページを作成</button></div>'+
    '</div>';
  document.body.appendChild(modal);
  var $modal=$(modal),$title=$modal.find('#emcore-page-title'),$slug=$modal.find('#emcore-page-slug'),$message=$modal.find('.emcore-page-create-message'),$create=$modal.find('[data-action="create"]');
  $title.val($source.data('name')||'');$slug.val(emcorePageSlug($source.data('slug')||''));
  function syncUrl(){$slug.val(emcorePageSlug($slug.val()));$modal.find('.emcore-url-preview code').text(emcorePublicUrl($slug.val()))}
  function close(){$modal.remove();$source.trigger('focus')}
  $slug.on('input',syncUrl);syncUrl();
  $modal.on('click','[data-action="cancel"]',close);
  $create.on('click',function(){
    var title=String($title.val()||'').trim(),slug=emcorePageSlug($slug.val());
    $title.removeClass('is-error');$slug.removeClass('is-error');msg($message,'',true);
    if(!title){$title.addClass('is-error').trigger('focus');msg($message,'ページタイトルを入力してください。',false);return}
    if(!slug){$slug.addClass('is-error').trigger('focus');msg($message,'URLスラッグを入力してください。',false);return}
    $create.prop('disabled',true).text('作成中…');$modal.find('[data-action="cancel"]').prop('disabled',true);
    post('emcore_create_page_from_tpl',{tpl:$source.data('id'),title:title,slug:slug,page_kind:$source.data('type')||'page'}).done(function(r){
      if(!r.success){$create.prop('disabled',false).text('ページを作成');$modal.find('[data-action="cancel"]').prop('disabled',false);msg($message,(r.data&&r.data.message)||emcoreAdmin.i18n.error,false);return}
      msg($message,r.data.message,true);$modal.find('.emcore-modal-actions').empty().append($('<a class="emcore-btn" target="_blank" rel="noopener">ページを見る ↗</a>').attr('href',r.data.view_url||'#')).append($('<button type="button" class="emcore-btn emcore-btn-primary">完了</button>').on('click',function(){location.reload()}));
    }).fail(function(){$create.prop('disabled',false).text('ページを作成');$modal.find('[data-action="cancel"]').prop('disabled',false);msg($message,emcoreAdmin.i18n.error,false)});
  });
  setTimeout(function(){$title.trigger('focus').select()},30);
}
$(document).on('click','.emcore-create-page',function(){emcoreOpenPageCreateModal($(this))});
$('#emcore-update-html').on('click',function(){
  var $b=$(this),$m=$('#emcore-html-msg'),html=$('#emcore-edit-html').val();
  post('emcore_save_template',{id:$b.data('id'),name:$b.data('name'),type:$b.data('type'),html:html}).done(function(r){
    if(r.success){msg($m,r.data.message,true);setTimeout(function(){location.reload()},800)}else msg($m,(r.data&&r.data.message)||emcoreAdmin.i18n.error,false);
  });
});
$('#emcore-new-cpt').on('click',function(){$('#emcore-new-cpt-form').slideToggle(150)});
$('#emcore-cpt-cancel').on('click',function(){$('#emcore-new-cpt-form').slideUp(150)});
$('#emcore-cpt-save').on('click',function(){
  var $m=$('#emcore-cpt-msg');
  post('emcore_cpt_create',{label:$('#emcore-cpt-label').val(),singular:$('#emcore-cpt-singular').val(),slug:$('#emcore-cpt-slug').val(),listing_mode:$('#emcore-cpt-listing-mode').val(),icon:$('#emcore-cpt-icon').val(),has_cat:$('#emcore-cpt-has-cat').is(':checked')?1:0}).done(function(r){
    if(r.success){msg($m,r.data.message,true);setTimeout(function(){location.reload()},600)}
    else msg($m,(r.data&&r.data.message)||emcoreAdmin.i18n.error,false);
  });
});
function emcoreSaveCptRouting(key){
  var $slug=$('.emcore-cpt-parent-slug[data-key="'+key+'"]'),$mode=$('.emcore-cpt-listing-mode[data-key="'+key+'"]');
  post('emcore_cpt_set_routing',{key:key,parent_slug:$slug.val(),listing_mode:$mode.val()}).done(function(r){
    if(r.success){location.reload()}else emcoreAlert((r.data&&r.data.message)||emcoreAdmin.i18n.error,'保存できません');
  });
}
$(document).on('change','.emcore-cpt-listing-mode',function(){emcoreSaveCptRouting($(this).data('key'))});
$(document).on('change','.emcore-cpt-parent-slug',function(){emcoreSaveCptRouting($(this).data('key'))});
$(document).on('click','.emcore-del-cpt',async function(){
  if(!await emcoreConfirmDelete('投稿タイプ'))return;
  var key=$(this).data('key');
  post('emcore_cpt_delete',{key:key,confirm_text:'DELETE'}).done(async function(r){
    if(r.success){location.reload();return}
    var d=r.data||{};
    if(!d.requires_confirmation){await emcoreAlert(d.message||emcoreAdmin.i18n.error,'削除できません');return}
    var pages=(d.pages||[]).map(function(name){return '・'+name}).join('\n');
    var warning='関連データが残っています。\n\n記事: '+(d.post_count||0)+'件';
    if(pages)warning+='\n一覧ページ（ゴミ箱へ移動）:\n'+pages;
    warning+='\n\n記事は完全に削除され、元に戻せません。メディアライブラリの画像は削除しません。\n投稿タイプと関連データを整理しますか？';
    if(!await emcoreConfirm(warning,{kicker:'EMERGE MONO / FORCE DELETE',title:'関連データも削除しますか？',confirmLabel:'完全に削除',danger:true}))return;
    post('emcore_cpt_delete',{key:key,confirm_text:'DELETE',force:1}).done(function(forced){
      if(forced.success)location.reload();else emcoreAlert((forced.data&&forced.data.message)||emcoreAdmin.i18n.error,'削除できません');
    });
  });
});
$(document).on('click','.emcore-delete-post',async function(){
  var $btn=$(this),id=$btn.data('id'),title=$btn.data('title')||'コンテンツ';
  if(!await emcoreConfirmDelete(title))return;
  $btn.prop('disabled',true);
  post('emcore_delete_post',{post_id:id,confirm_text:'DELETE'}).done(function(r){
    if(r.success){var $row=$btn.closest('tr');$row.css({opacity:0,transform:'translateY(-4px)'});setTimeout(function(){location.reload()},240)}
    else{$btn.prop('disabled',false);emcoreAlert((r.data&&r.data.message)||emcoreAdmin.i18n.error,'削除できません')}
  }).fail(function(){$btn.prop('disabled',false);emcoreAlert(emcoreAdmin.i18n.error,'通信エラー')});
});
$(document).on('click','.emcore-create-archive-page',function(){
  var $b=$(this);$b.prop('disabled',true);
  post('emcore_create_archive_page',{key:$b.data('key')}).done(function(r){
    $b.prop('disabled',false);
    if(r.success){var u=r.data.view_url||'';emcoreConfirm(r.data.message+(u?'\n'+u:''),{kicker:'EMERGE MONO / COMPLETE',title:'一覧ページを作成しました',confirmLabel:'ページを開く',cancelLabel:'閉じる'}).then(function(open){if(open&&u)window.open(u,'_blank')})}
    else emcoreAlert((r.data&&r.data.message)||emcoreAdmin.i18n.error,'作成できません');
  }).fail(function(){$b.prop('disabled',false);emcoreAlert(emcoreAdmin.i18n.error,'通信エラー')});
});
$(document).on('change','.emcore-link-tpl',function(){
  var $el=$(this);
  post('emcore_cpt_set_template',{key:$el.data('key'),which:$el.data('which'),tpl:$el.val()});
});
$(document).on('change','.emcore-cpt-filter',function(){
  var $el=$(this),on=$el.is(':checked');$el.closest('label').find('b').text(on?'ON':'OFF');
  post('emcore_cpt_set_filter',{key:$el.data('key'),enabled:on?1:0}).done(function(r){if(!r.success){$el.prop('checked',!on);$el.closest('label').find('b').text(!on?'ON':'OFF');emcoreAlert((r.data&&r.data.message)||emcoreAdmin.i18n.error,'保存できません')}});
});
function emcoreOpenPostCreateModal($source){
  var modal=document.createElement('div');modal.className='emcore-modal emcore-page-create-modal emcore-post-create-modal';modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');
  modal.innerHTML='<div class="emcore-modal-card"><div class="emcore-modal-kicker">NEW CONTENT / EMERGE MONO</div><h3>新しいコンテンツを作成</h3><p class="emcore-modal-lead">タイトルとURLを決めてから、専用編集画面へ進みます。</p><label for="emcore-post-title">タイトル</label><input type="text" id="emcore-post-title" class="emcore-input" autocomplete="off"><label for="emcore-post-slug">URLスラッグ</label><div class="emcore-slug-field"><span>/</span><input type="text" id="emcore-post-slug" class="emcore-input" inputmode="url" autocomplete="off" spellcheck="false"><span>/</span></div><p class="emcore-help">半角英数字・ハイフン・アンダーバーが使用できます。</p><div class="emcore-post-create-message emcore-msg" role="status"></div><div class="emcore-modal-actions"><button type="button" class="emcore-btn" data-action="cancel">キャンセル</button><button type="button" class="emcore-btn emcore-btn-primary" data-action="create">作成して編集</button></div></div>';
  document.body.appendChild(modal);
  var $modal=$(modal),$title=$modal.find('#emcore-post-title'),$slug=$modal.find('#emcore-post-slug'),$message=$modal.find('.emcore-post-create-message'),$create=$modal.find('[data-action="create"]');
  function close(){$modal.remove();$source.trigger('focus')}
  $title.on('input',function(){if(!$slug.data('edited'))$slug.val(emcorePageSlug($title.val()))});$slug.on('input',function(){$slug.data('edited',true).val(emcorePageSlug($slug.val()))});
  $modal.on('click','[data-action="cancel"]',close);
  $create.on('click',function(){var title=String($title.val()||'').trim(),slug=emcorePageSlug($slug.val());$title.removeClass('is-error');$slug.removeClass('is-error');msg($message,'',true);if(!title){$title.addClass('is-error').trigger('focus');msg($message,'タイトルを入力してください。',false);return}if(!slug){$slug.addClass('is-error').trigger('focus');msg($message,'URLスラッグを入力してください。',false);return}$create.prop('disabled',true).text('作成中…');$modal.find('[data-action="cancel"]').prop('disabled',true);post('emcore_create_post',{cpt:$source.data('cpt'),title:title,slug:slug}).done(function(r){if(r.success&&r.data.edit_url){location.href=r.data.edit_url;return}$create.prop('disabled',false).text('作成して編集');$modal.find('[data-action="cancel"]').prop('disabled',false);msg($message,(r.data&&r.data.message)||emcoreAdmin.i18n.error,false)}).fail(function(){$create.prop('disabled',false).text('作成して編集');$modal.find('[data-action="cancel"]').prop('disabled',false);msg($message,emcoreAdmin.i18n.error,false)})});
  setTimeout(function(){$title.trigger('focus')},30);
}
$('#emcore-new-post').on('click',function(){emcoreOpenPostCreateModal($(this))});

function emcoreSlidesSync($box){
  var urls=[];
  $box.find('.emcore-slide-item img').each(function(){ urls.push($(this).attr('src')); });
  $box.find('.emcore-client-val').val(JSON.stringify(urls));
}
function emcoreSlideMarkup(url){
  return '<div class="emcore-slide-item" draggable="true"><img src="'+url+'" alt=""><div class="emcore-item-actions"><button type="button" class="emcore-btn emcore-btn-sm emcore-slide-replace">変更</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-prev">↑</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-next">↓</button><button type="button" class="emcore-btn emcore-btn-sm emcore-slide-del">削除</button></div></div>';
}
$(document).on('click','.emcore-slide-add',function(){
  var key=$(this).data('key');
  var $box=$(this).closest('.emcore-slides');
  var frame=wp.media({title:'スライダー画像',multiple:true,library:{type:'image'}});
  frame.on('select',function(){
    frame.state().get('selection').each(function(att){
      att=att.toJSON();
      $box.find('.emcore-slides-list').append(emcoreSlideMarkup(att.url));
    });
    emcoreSlidesSync($box);
  });
  frame.open();
});
$(document).on('click','.emcore-slide-replace',function(){
  var $item=$(this).closest('.emcore-slide-item'),$box=$(this).closest('.emcore-slides');
  var frame=wp.media({title:'スライダー画像を変更',multiple:false,library:{type:'image'}});
  frame.on('select',function(){$item.find('img').attr('src',frame.state().get('selection').first().toJSON().url);emcoreSlidesSync($box)});frame.open();
});
$(document).on('click','.emcore-slide-del',function(){
  var $box=$(this).closest('.emcore-slides');
  $(this).closest('.emcore-slide-item').remove();
  emcoreSlidesSync($box);
});
function emcoreAccordionSync($box){
  var items=[];$box.find('.emcore-accordion-row').each(function(){items.push({question:$(this).find('.emcore-accordion-question').val(),answer:$(this).find('.emcore-accordion-answer').val()})});
  $box.find('.emcore-client-val').val(JSON.stringify(items));
}
function emcoreAccordionMarkup(){return '<div class="emcore-accordion-row" draggable="true"><input type="text" class="emcore-input emcore-accordion-question" placeholder="質問"><textarea class="emcore-textarea emcore-accordion-answer" rows="3" placeholder="回答"></textarea><div class="emcore-item-actions"><button type="button" class="emcore-btn emcore-btn-sm emcore-move-prev">↑</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-next">↓</button><button type="button" class="emcore-btn emcore-btn-sm emcore-accordion-del">削除</button></div></div>'}
$(document).on('click','.emcore-accordion-add',function(){var $box=$(this).closest('.emcore-accordion-editor');$box.find('.emcore-accordion-list').append(emcoreAccordionMarkup());emcoreAccordionSync($box)});
$(document).on('click','.emcore-accordion-del',function(){var $box=$(this).closest('.emcore-accordion-editor');$(this).closest('.emcore-accordion-row').remove();emcoreAccordionSync($box)});
$(document).on('input','.emcore-accordion-question,.emcore-accordion-answer',function(){emcoreAccordionSync($(this).closest('.emcore-accordion-editor'))});
function emcoreMenuSync($box){var items=[];$box.find('.emcore-menu-row').each(function(){items.push({label:$(this).find('.emcore-menu-label').val(),url:$(this).find('.emcore-menu-url').val(),target:$(this).find('.emcore-menu-target').is(':checked')?'_blank':''})});$box.find('.emcore-client-val,.emcore-shared-val').first().val(JSON.stringify(items))}
function emcoreMenuMarkup(){return '<div class="emcore-menu-row" draggable="true"><input type="text" class="emcore-input emcore-menu-label" placeholder="表示名"><input type="text" class="emcore-input emcore-menu-url" placeholder="/page/ または https://..."><label class="emcore-check"><input type="checkbox" class="emcore-menu-target"> 新しいタブ</label><div class="emcore-item-actions"><button type="button" class="emcore-btn emcore-btn-sm emcore-move-prev">↑</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-next">↓</button><button type="button" class="emcore-btn emcore-btn-sm emcore-menu-del">削除</button></div></div>'}
$(document).on('click','.emcore-menu-add',function(){var $box=$(this).closest('.emcore-menu-editor');$box.find('.emcore-menu-list').append(emcoreMenuMarkup());emcoreMenuSync($box)});
$(document).on('click','.emcore-menu-del',function(){var $box=$(this).closest('.emcore-menu-editor');$(this).closest('.emcore-menu-row').remove();emcoreMenuSync($box)});
$(document).on('input change','.emcore-menu-label,.emcore-menu-url,.emcore-menu-target',function(){emcoreMenuSync($(this).closest('.emcore-menu-editor'))});
function emcoreCollectionSync($box){var kind=$box.data('kind'),items=[];$box.find('.emcore-collection-row').each(function(){var item={};$(this).find('[data-collection-field]').each(function(){item[$(this).data('collection-field')]=$(this).val()});if(kind==='table'){item.cells=[];$(this).find('[data-collection-cell]').each(function(){item.cells.push($(this).val())})}items.push(item)});$box.find('.emcore-client-val,.emcore-shared-val').first().val(JSON.stringify(items))}
function emcoreCollectionBlankRow($box){var $sample=$box.find('.emcore-collection-row').first(),$row;if($sample.length){$row=$sample.clone();$row.find('input,textarea').val('');}else{var kind=$box.data('kind'),fields={list:['text'],steps:['title','description'],gallery:['image','alt','url'],tabs:['label','content'],table:['cell']}[kind]||['field_1'];$row=$('<div class="emcore-collection-row" draggable="true"></div>');fields.forEach(function(name){if(kind==='table')$row.append('<input type="text" class="emcore-input" data-collection-cell="1" placeholder="セル">');else if(name==='description'||name==='content')$row.append('<textarea class="emcore-textarea" data-collection-field="'+name+'" rows="2"></textarea>');else $row.append('<input type="text" class="emcore-input" data-collection-field="'+name+'">')});$row.append('<div class="emcore-item-actions"><button type="button" class="emcore-btn emcore-btn-sm emcore-move-prev">↑</button><button type="button" class="emcore-btn emcore-btn-sm emcore-move-next">↓</button><button type="button" class="emcore-btn emcore-btn-sm emcore-collection-del">削除</button></div>')}return $row}
$(document).on('click','.emcore-collection-add',function(){var $box=$(this).closest('.emcore-collection-editor');$box.find('.emcore-collection-list').append(emcoreCollectionBlankRow($box));emcoreCollectionSync($box)});
$(document).on('click','.emcore-collection-del',function(){var $box=$(this).closest('.emcore-collection-editor');$(this).closest('.emcore-collection-row').remove();emcoreCollectionSync($box)});
$(document).on('input change','.emcore-collection-row [data-collection-field],.emcore-collection-row [data-collection-cell]',function(){emcoreCollectionSync($(this).closest('.emcore-collection-editor'))});
$(document).on('click','.emcore-move-prev,.emcore-move-next',function(){
	var $item=$(this).closest('.emcore-slide-item,.emcore-accordion-row,.emcore-menu-row,.emcore-collection-row'),prev=$(this).hasClass('emcore-move-prev');
  if(prev&&$item.prev().length)$item.insertBefore($item.prev());else if(!prev&&$item.next().length)$item.insertAfter($item.next());
	var $slides=$item.closest('.emcore-slides');if($slides.length)emcoreSlidesSync($slides);var $acc=$item.closest('.emcore-accordion-editor');if($acc.length)emcoreAccordionSync($acc);var $menu=$item.closest('.emcore-menu-editor');if($menu.length)emcoreMenuSync($menu);var $collection=$item.closest('.emcore-collection-editor');if($collection.length)emcoreCollectionSync($collection);
});
var emcoreDragged=null;
$(document).on('dragstart','.emcore-slide-item,.emcore-accordion-row,.emcore-menu-row,.emcore-collection-row',function(){emcoreDragged=this;this.classList.add('is-dragging')});
$(document).on('dragend','.emcore-slide-item,.emcore-accordion-row,.emcore-menu-row,.emcore-collection-row',function(){this.classList.remove('is-dragging');emcoreDragged=null});
$(document).on('dragover','.emcore-slide-item,.emcore-accordion-row,.emcore-menu-row,.emcore-collection-row',function(e){e.preventDefault();if(!emcoreDragged||emcoreDragged===this||emcoreDragged.parentNode!==this.parentNode)return;var r=this.getBoundingClientRect();this.parentNode.insertBefore(emcoreDragged,(e.originalEvent.clientY<r.top+r.height/2)?this:this.nextSibling)});
$(document).on('drop','.emcore-slide-item,.emcore-accordion-row,.emcore-menu-row,.emcore-collection-row',function(e){e.preventDefault();var $p=$(this).parent();var $slides=$p.closest('.emcore-slides');if($slides.length)emcoreSlidesSync($slides);var $acc=$p.closest('.emcore-accordion-editor');if($acc.length)emcoreAccordionSync($acc);var $menu=$p.closest('.emcore-menu-editor');if($menu.length)emcoreMenuSync($menu);var $collection=$p.closest('.emcore-collection-editor');if($collection.length)emcoreCollectionSync($collection)});
$(document).on('click','.emcore-pick-client-image',function(){
  var key=$(this).data('key'), responsive=String($(this).data('responsive'))==='1';
  var frame=wp.media({title:'画像を選択',multiple:false,library:{type:'image'}});
  frame.on('select',function(){
    var att=frame.state().get('selection').first().toJSON();
    var url=att.url;
    if(responsive){
      var $hidden=$('.emcore-client-val[data-key="'+key+'"]'), value={default:url,sources:{}};
      try{var old=JSON.parse($hidden.val()||'{}');if(old.sources)value.sources=old.sources}catch(e){}
      $hidden.val(JSON.stringify(value));$('.emcore-responsive-images[data-key="'+key+'"] [data-media="default"]').val(url);
    }else $('.emcore-client-val[data-key="'+key+'"]').val(url);
    $('.emcore-client-id[data-key="'+key+'"]').val(att.id);
    var $p=$('.emcore-img-preview[data-key="'+key+'"]');
    $p.html('<img src="'+url+'" alt=""><span class="emcore-thumb-overlay">変更</span>');
    $('.emcore-remove-client-image[data-key="'+key+'"]').prop('hidden',false);
  });
  frame.open();
});
$(document).on('click','.emcore-remove-client-image',function(){
  var key=$(this).data('key'),responsive=String($(this).data('responsive'))==='1';
  $('.emcore-client-val[data-key="'+key+'"]').val('');
  $('.emcore-client-id[data-key="'+key+'"]').val('');
  $('.emcore-img-preview[data-key="'+key+'"]').html('<span class="emcore-thumb-placeholder"><span class="dashicons dashicons-format-image"></span><b>クリックして画像を選択</b></span>');
  if(responsive){$('.emcore-responsive-images[data-key="'+key+'"] .emcore-responsive-src').val('')}
  $('.emcore-pick-client-image[data-key="'+key+'"]').not('.emcore-img-preview').text('画像を選択');
  $(this).prop('hidden',true);
});
$(document).on('input change','.emcore-responsive-src',function(){
  var $box=$(this).closest('.emcore-responsive-images'),key=$box.data('key'),value={default:'',sources:{}};
  $box.find('.emcore-responsive-src').each(function(){var media=$(this).data('media'),url=$(this).val();if(media==='default')value.default=url;else value.sources[media]=url});
  $('.emcore-client-val[data-key="'+key+'"]').val(JSON.stringify(value));
});
$('#emcore-save-client').on('click',function(){
  var $b=$(this), $m=$('#emcore-client-msg');
  var fields={};
  $('.emcore-client-val').each(function(){
    fields[$(this).data('key')]=$(this).val();
  });
  $('.emcore-client-id').each(function(){
    fields[$(this).data('key')+'_id']=$(this).val();
  });
  var cats=[];
  $('.emcore-cat:checked').each(function(){ cats.push($(this).val()); });
  var seo={};$('.emseo-post-fields[data-post-id="'+$b.data('id')+'"] [data-emseo]').each(function(){seo[$(this).data('emseo')]=$(this).is(':checkbox')?($(this).is(':checked')?'1':'0'):$(this).val()});
  post('emcore_save_post_fields',{
    cpt:$b.data('cpt'),
    post_id:$b.data('id'),
    status:$('#emcore-client-status').val(),
    fields:JSON.stringify(fields),
    cats:cats.join(','),
    emseo:JSON.stringify(seo)
  }).done(function(r){
    msg($m, r.success?r.data.message:((r.data&&r.data.message)||emcoreAdmin.i18n.error), r.success);
  });
});

/* -------- mark mode (agency) -------- */
function emcoreFileNameFromUrl(url){
  if(!url) return '';
  try{
    url=String(url).split('#')[0].split('?')[0];
    var parts=url.split('/');
    var name=decodeURIComponent(parts[parts.length-1]||'');
    return name || url;
  }catch(e){ return url; }
}
function emcoreExtractUrlFromEl(el){
  if(!el) return '';
  var tag=(el.tagName||'').toLowerCase();
  if(tag==='img' || tag==='video' || tag==='source' || tag==='audio'){
    return el.getAttribute('src')||el.getAttribute('currentSrc')||el.getAttribute('data-src')||'';
  }
  if(tag==='video' && el.poster) return el.getAttribute('poster')||'';
  var styleBg=(el.style&&el.style.backgroundImage)||'';
  var m=styleBg.match(/url\(["']?([^"')]+)["']?\)/);
  if(m) return m[1];
  try{
    var cs=el.ownerDocument.defaultView.getComputedStyle(el);
    if(cs && cs.backgroundImage){
      var m2=cs.backgroundImage.match(/url\(["']?([^"')]+)["']?\)/);
      if(m2) return m2[1];
    }
  }catch(e){}
  return '';
}
function emcoreLayerLabel(el){
  var tag=(el.tagName||'').toLowerCase();
  var cls=(typeof el.className==='string'?el.className:'').trim().split(/\s+/).filter(Boolean).slice(0,2).join('.');
  var type='text';
  var url=emcoreExtractUrlFromEl(el);
  if(tag==='img') type='img';
  else if(tag==='video' || tag==='source') type='video';
  else if(tag==='audio') type='audio';
  else if(url) type='bg';
  else if(tag==='a') type='link';
  var text='';
  if(type==='text'||type==='link'){ text=(el.innerText||'').replace(/\s+/g,' ').trim().slice(0,40); }
  else if(url){ text=emcoreFileNameFromUrl(url); }
	var href=tag==='a'?(el.getAttribute('href')||''):'';
	var region='OTHER',owner=el.closest&&el.closest('header,nav,main,footer,section');
	if(owner){region=(owner.tagName||'').toUpperCase();if(region==='SECTION'){var hint=owner.getAttribute('aria-label')||owner.id||(typeof owner.className==='string'?owner.className.split(/\s+/)[0]:'');region=hint?'SECTION · '+String(hint).toUpperCase():'SECTION';}}
	var edit=el.getAttribute('data-em-editable')||'';
  return { tag:tag, name:tag+(cls?'.'+cls:''), type:type, text:text, url:url, href:href, region:region, marked:edit, scope:el.getAttribute('data-em-scope')||'', key:el.getAttribute('data-em-key')||'', el:el };
}
function emcoreCollectLayers(doc){
  var seen=new Set(); var list=[];
  function add(el){
    if(!el||seen.has(el)) return;
    var tag=(el.tagName||'').toLowerCase();
    if(['script','style','link','meta','br','svg','path'].indexOf(tag)>=0) return;
    seen.add(el); list.push(emcoreLayerLabel(el));
  }
  doc.querySelectorAll('[data-em-editable]').forEach(add);
  doc.querySelectorAll('[data-em-repeat="posts"]').forEach(add);
  doc.querySelectorAll('img,video,source,audio').forEach(add);
  doc.querySelectorAll('h1,h2,h3,h4,p').forEach(add);
  var all=doc.body?doc.body.querySelectorAll('*'):[];
  all.forEach(function(el){
    try{
      var st=el.getAttribute('style')||'';
      if(/background-image/i.test(st)) add(el);
      else {
        var cs=doc.defaultView.getComputedStyle(el);
        if(cs && cs.backgroundImage && cs.backgroundImage!=='none' && cs.backgroundImage.indexOf('url(')!==-1){
          var r=el.getBoundingClientRect();
          if(r.width>40 && r.height>40) add(el);
        }
      }
    }catch(e){}
  });
  doc.querySelectorAll('a[href]').forEach(function(el){ if((el.innerText||'').trim()) add(el); });
  return list;
}
function emcoreGuessKind(el){
	var component=emcoreDetectComponent(el);
	if(component) return component.kind;
  var tag=(el.tagName||'').toLowerCase();
  var url=emcoreExtractUrlFromEl(el);
  if(tag==='img' || tag==='picture') return 'image';
  if(url) return 'bg';
  if(tag==='a') return 'href';
  return 'text';
}
function emcoreSupportsFont(kind){
  return kind==='text' || kind==='href';
}

function emcoreDetectComponent(el){
  if(!el||!el.closest)return null;
	var postList=el.closest('[data-em-component="post-list"],[data-em-repeat="posts"]');
	if(postList)return {kind:'repeat',target:postList,label:postList.getAttribute('data-em-label')||'投稿一覧',message:'投稿一覧カードを検出しました',explicit:true};
	var defs=[['list','.emcore-list,[data-em-component="list"],[data-em-editable="list"]','リスト'],['steps','.emcore-steps,[data-em-component="steps"],[data-em-editable="steps"]','ステップ'],['gallery','.emcore-gallery,[data-em-component="gallery"],[data-em-editable="gallery"]','ギャラリー'],['tabs','.emcore-tabs,[data-em-component="tabs"],[data-em-editable="tabs"]','タブ'],['table','.emcore-table,[data-em-component="table"],[data-em-editable="table"]','表'],['repeater','.emcore-repeater,[data-em-component="repeater"],[data-em-editable="repeater"]','カード一覧']];
	for(var di=0;di<defs.length;di++){var dynamicRoot=el.closest(defs[di][1]);if(dynamicRoot)return {kind:defs[di][0],target:dynamicRoot,label:dynamicRoot.getAttribute('data-em-label')||defs[di][2],message:defs[di][2]+'全体を検出しました',explicit:true}}
	var menu=el.closest('.emcore-menu,[data-em-component="menu"],[data-em-editable="menu"]');
	if(menu) return {kind:'menu',target:menu,label:menu.getAttribute('data-em-label')||'メインメニュー',message:'メニュー全体を検出しました',explicit:true};
	var form=el.closest('form.emcore-form,form[data-em-component="form"]');
  if(form) return {kind:'form',target:form,label:form.getAttribute('data-em-label')||'お問い合わせフォーム',message:'フォーム全体を検出しました',explicit:true};
  var slider=el.closest('.emcore-slider,[data-em-component="slider"],[data-em-editable="slides"],.swiper-wrapper,.splide__list,.slick-track,[data-slider],[data-carousel],.slider-track,.slider-wrapper,#slider');
  if(!slider){
    for(var s=el.parentElement,si=0;s&&si<5;s=s.parentElement,si++){
      var kids=Array.from(s.children||[]), slideKids=kids.filter(function(k){return /(^|\s)(swiper-slide|splide__slide|slick-slide|slider-slide|slide)(\s|$)/i.test(typeof k.className==='string'?k.className:'')});
      if(slideKids.length>=2){slider=s;break;}
    }
  }
  if(slider) return {kind:'slides',target:slider,label:slider.getAttribute('data-em-label')||'スライダー',message:'スライダー全体を検出しました',explicit:slider.classList.contains('emcore-slider')||slider.hasAttribute('data-em-component')};
  var accordion=el.closest('.emcore-accordion,[data-em-component="accordion"],[data-em-editable="accordion"]');
  if(accordion) return {kind:'accordion',target:accordion,label:accordion.getAttribute('data-em-label')||'FAQ／アコーディオン',message:'FAQ／アコーディオン全体を検出しました',explicit:true};
  var item=el.closest('details,.faq-item,.accordion-item,.emcore-accordion-item,[data-accordion-item],[class*="faq__item"],[class*="accordion__item"]');
  if(item){
    var container=item.parentElement;
    if(container) return {kind:'accordion',target:container,item:item,label:'FAQ／アコーディオン',message:'FAQ／アコーディオン全体を検出しました'};
  }
  var repeater=el.closest('.emcore-repeater,[data-em-component="repeater"],[data-em-component="post-list"]');
  if(repeater) return {kind:'repeat',target:repeater,label:repeater.getAttribute('data-em-label')||'繰り返しコンテンツ',message:'繰り返しコンテンツ全体を検出しました',explicit:true};
  return null;
}
function emcoreConfigureComponent(target,kind){
	if(['list','steps','gallery','tabs','table','repeater'].indexOf(kind)!==-1){
		target.setAttribute('data-em-component',kind);
		var config={list:['.emcore-list-item','data-em-list-item'],steps:['.emcore-step-item','data-em-step-item'],gallery:['.emcore-gallery-item','data-em-gallery-item'],tabs:['.emcore-tab-item','data-em-tab-item'],table:['.emcore-table-row','data-em-table-row'],repeater:['.emcore-repeater-item','data-em-repeater-item']}[kind],items=target.querySelectorAll(config[0]);
		items.forEach(function(item){item.setAttribute(config[1],'1')});
		if(kind==='list')items.forEach(function(item){(item.querySelector('.emcore-list-text')||item).setAttribute('data-em-list-text','1')});
		if(kind==='steps')items.forEach(function(item){var n=item.querySelector('.emcore-step-index'),t=item.querySelector('.emcore-step-title'),d=item.querySelector('.emcore-step-description');if(n)n.setAttribute('data-em-step-index','1');if(t)t.setAttribute('data-em-step-title','1');if(d)d.setAttribute('data-em-step-description','1')});
		if(kind==='gallery')items.forEach(function(item){var img=item.matches('img')?item:item.querySelector('img'),a=item.matches('a')?item:item.querySelector('a');if(img)img.setAttribute('data-em-gallery-image','1');if(a)a.setAttribute('data-em-gallery-link','1')});
		if(kind==='tabs')items.forEach(function(item){var t=item.querySelector('.emcore-tab-trigger,[role="tab"],button'),p=item.querySelector('.emcore-tab-content,[role="tabpanel"]');if(t)t.setAttribute('data-em-tab-trigger','1');if(p)p.setAttribute('data-em-tab-content','1')});
		if(kind==='table')items.forEach(function(item){item.querySelectorAll('.emcore-table-cell,th,td').forEach(function(cell){cell.setAttribute('data-em-table-cell','1')})});
		if(kind==='repeater')items.forEach(function(item){item.querySelectorAll('.emcore-repeater-field').forEach(function(field,index){if(!field.getAttribute('data-em-repeater-field'))field.setAttribute('data-em-repeater-field','field_'+String(index+1).padStart(2,'0'))})});
		return target;
	}
	if(kind==='menu'){
		target.setAttribute('data-em-component','menu');
		var items=target.querySelectorAll('.emcore-menu-item');
		if(!items.length)items=target.querySelectorAll('a[href]');
		items.forEach(function(item){var root=item.classList&&item.classList.contains('emcore-menu-item')?item:(item.closest?item.closest('.emcore-menu-item'):null)||item;root.setAttribute('data-em-menu-item','1');var link=(root.matches&&root.matches('a[href]'))?root:root.querySelector('a[href]');if(link)link.setAttribute('data-em-menu-label','1')});
		return target;
	}
  if(kind==='form'){
    target.setAttribute('data-em-component','form');
    target.querySelectorAll('input,textarea,select').forEach(function(field,index){
      if(!field.name && field.type!=='submit' && field.type!=='button')field.name='field_'+(index+1);
    });
    return target;
  }
  if(kind==='slides'){
    target.setAttribute('data-em-component','slider');
    return target;
  }
  if(kind!=='accordion') return target;
  target.setAttribute('data-em-component','accordion');
  var items=target.querySelectorAll('.emcore-accordion-item,details,.faq-item,.accordion-item,[data-accordion-item],[class*="faq__item"],[class*="accordion__item"]');
  if(!items.length&&target.firstElementChild)items=[target.firstElementChild];
  Array.from(items).forEach(function(item){
    item.setAttribute('data-em-accordion-item','1');
    var q=item.querySelector('.emcore-accordion-trigger,summary,.faq-question,.accordion-header,.accordion-title,[data-question],button,h2,h3,h4')||item.firstElementChild;
    var a=item.querySelector('.emcore-accordion-content,.faq-answer,.accordion-content,.accordion-body,[data-answer]');
    if(!a){var children=Array.from(item.children||[]);a=children.filter(function(n){return n!==q})[0]||null;}
    if(q)q.setAttribute('data-em-accordion-question','1');
    if(a)a.setAttribute('data-em-accordion-answer','1');
  });
  return target;
}
function emcoreGuessKey(el, kind){
  var cls=(typeof el.className==='string'?el.className:'').toLowerCase();
  var tag=(el.tagName||'').toLowerCase();
  if(kind==='image' || kind==='bg'){
    var hint=(cls+' '+(el.id||'')+' '+emcoreFileNameFromUrl(emcoreExtractUrlFromEl(el))).toLowerCase();
    if(/logo/.test(hint)) return 'logo';
    if(/standee|tachie|character|profile/.test(hint)) return 'standee';
    if(/hero|mainvisual|main-visual|fv/.test(hint)) return 'hero_image';
    if(/thumb|thumbnail|card/.test(hint)) return 'thumbnail';
    return 'image';
  }
  if(kind==='href'){
    if(/x|twitter/.test(cls+(el.getAttribute('href')||''))) return 'sns_x';
    if(/iriam/.test(cls+(el.getAttribute('href')||''))) return 'sns_iriam';
    return 'link';
  }
  if(tag==='h1' || /name|title/.test(cls)) return 'title';
  if(/furi|yomi|reading/.test(cls)) return 'reading';
  if(/en|english/.test(cls)) return 'name_en';
  if(tag==='p' || /bio|desc/.test(cls)) return 'content';
  return 'text';
}

function emcoreAutoSlug(value){
  return String(value||'').toLowerCase().replace(/[^a-z0-9_-]+/g,'_').replace(/^_+|_+$/g,'').slice(0,36);
}
function emcoreAutoRegion(el){
  var region=el.closest&&el.closest('header,footer,nav');
  if(region) return region.tagName.toLowerCase();
  return '';
}
function emcoreAutoLabel(el,kind,index){
  var tag=(el.tagName||'').toLowerCase();
  var text=(el.innerText||el.getAttribute('alt')||'').replace(/\s+/g,' ').trim().slice(0,24);
  if(kind==='image') return /logo/i.test((el.className||'')+' '+(el.getAttribute('src')||''))?'ロゴ画像':'画像 '+index;
  if(kind==='href') return (text||'リンク')+' のリンク先';
  if(tag==='h1') return 'メイン見出し';
  if(/^h[2-6]$/.test(tag)) return text||('見出し '+index);
  return text||('テキスト '+index);
}
function emcoreAutoDetect(doc){
	var candidates=[]; var seen=new Set(),logical=new Map();
	var selectors='.emcore-slider,.emcore-accordion,.emcore-repeater,.emcore-menu,.emcore-list,.emcore-steps,.emcore-gallery,.emcore-tabs,.emcore-table,form.emcore-form,[data-em-component="slider"],[data-em-component="accordion"],[data-em-component="repeater"],[data-em-component="post-list"],[data-em-component="menu"],[data-em-component="list"],[data-em-component="steps"],[data-em-component="gallery"],[data-em-component="tabs"],[data-em-component="table"],form[data-em-component="form"]';
  doc.querySelectorAll(selectors).forEach(function(el){
    var c=emcoreDetectComponent(el);if(!c||seen.has(c.target))return;
    seen.add(c.target);
		var key=c.target.getAttribute('data-em-key')||'',scope=c.target.getAttribute('data-em-scope')||'',configured=!!key||(c.kind==='repeat'&&c.target.hasAttribute('data-em-repeat'));
		var identity=key?(c.kind+'|'+scope+'|'+key):'';
		if(identity&&logical.has(identity)){
			var existing=logical.get(identity);existing.targets.push(c.target);existing.configured=existing.configured||configured;return;
		}
		var candidate={el:c.target,targets:[c.target],kind:c.kind,label:c.label,component:true,configured:configured};
		candidates.push(candidate);if(identity)logical.set(identity,candidate);
  });
  return candidates;
}

function emcoreUsedKeys(doc){
  var keys=[];
  if(!doc) return keys;
  doc.querySelectorAll('[data-em-key]').forEach(function(n){
    var k=n.getAttribute('data-em-key');
    if(k && keys.indexOf(k)===-1) keys.push(k);
  });
  return keys;
}
function emcoreElementPath(el){
  var parts=[];
  for(var n=el;n&&n.tagName&&parts.length<6;n=n.parentElement){
    var p=n.tagName.toLowerCase();if(n.id)p+='#'+n.id;else if(typeof n.className==='string'&&n.className.trim())p+='.'+n.className.trim().split(/\s+/).slice(0,2).join('.');parts.unshift(p);
  }
  return parts.join(' > ');
}

function emcoreInternalKey(doc,kind){
	var prefix={slides:'slider',accordion:'accordion',menu:'menu',list:'list',steps:'steps',gallery:'gallery',tabs:'tabs',table:'table',repeater:'repeater',form:'form',repeat:'repeater',image:'image',bg:'background',href:'link',text:'text'}[kind]||'field';
  var used=emcoreUsedKeys(doc),index=1,key='';
  do{key='emcore_'+prefix+'_'+String(index).padStart(2,'0');index++;}while(used.indexOf(key)!==-1);
  return key;
}
function emcoreDefaultComponentTitle(kind){
	return {slides:'メインスライダー',accordion:'よくある質問',menu:'メインメニュー',list:'リスト',steps:'ステップ',gallery:'ギャラリー',tabs:'タブ',table:'表',repeater:'カード一覧',form:'お問い合わせフォーム',repeat:'一覧コンテンツ'}[kind]||'編集項目';
}
function emcoreResponsivePictureData(el){
  if(!el||!el.tagName) return null;
  var picture=(el.tagName.toLowerCase()==='picture')?el:(el.closest?el.closest('picture'):null);
  if(!picture) return null;
  var img=picture.querySelector('img');
  var sources={};
  picture.querySelectorAll('source[media]').forEach(function(source){
    var media=source.getAttribute('media')||'';
    var src=source.getAttribute('srcset')||source.getAttribute('src')||'';
    if(media&&src) sources[media]=src;
  });
  if(!Object.keys(sources).length) return null;
  return {
    target:picture,
    preview:img||el,
    variants:{
      default:img?(img.getAttribute('src')||img.getAttribute('data-src')||''):'',
      sources:sources
    }
  };
}
function emcoreOpenQuickMarkDialog(el,applyCb,doc){
  var detected=emcoreDetectComponent(el);
  var target=detected?detected.target:el;
  var kind=target.getAttribute('data-em-editable')||(detected?detected.kind:emcoreGuessKind(target));
  var responsive=kind==='image'?emcoreResponsivePictureData(el):null;
  var legacyTarget=target;
  if(responsive) target=responsive.target;
  var configured=target.hasAttribute('data-em-key')||target.hasAttribute('data-em-repeat');
	var dynamic=['slides','accordion','menu','list','steps','gallery','tabs','table','repeater','form','repeat'].indexOf(kind)!==-1;
  var existingKey=target.getAttribute('data-em-key')||legacyTarget.getAttribute('data-em-key')||'';
  var label=target.getAttribute('data-em-label')||legacyTarget.getAttribute('data-em-label')||emcoreAutoLabel(responsive?responsive.preview:target,kind,1);
  if(dynamic&&!target.getAttribute('data-em-label'))label=emcoreDefaultComponentTitle(kind);
	var typeLabel={text:'文字',image:'画像',bg:'背景画像',href:'リンク',slides:'スライダー',accordion:'アコーディオン',menu:'メニュー',list:'リスト',steps:'ステップ',gallery:'ギャラリー',tabs:'タブ',table:'表',repeater:'カード一覧',form:'フォーム',repeat:'繰り返しコンテンツ'}[kind]||'項目';
  var previewUrl=emcoreExtractUrlFromEl(responsive?responsive.preview:target),previewHtml=previewUrl?'<div class="emcore-modal-preview"><img src="'+previewUrl.replace(/"/g,'')+'" alt="選択中"></div>':'';
  var wrap=document.createElement('div');wrap.className='emcore-modal emcore-system-modal emcore-quick-mark-modal';
  var safeLabel=String(label).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
  var fontSummary=emcoreSupportsFont(kind)?'<span>フォント</span><strong>WordPressフォントを選択可能</strong>':'';
  wrap.innerHTML='<div class="emcore-modal-card"><div class="emcore-modal-kicker">EDITABLE AREA / EMERGE MONO</div><h3>'+(configured?'編集可能項目を確認':'この部分を編集可能にしますか？')+'</h3><p class="emcore-modal-lead">'+typeLabel+'として内容編集画面に追加します。キー・保存先・適用範囲はCoreが自動で設定します。'+(responsive?' PC用・スマートフォン用など、画面幅で切り替わる画像もまとめて引き継ぎます。':'')+'</p>'+previewHtml+'<div class="emcore-quick-summary"><span>種類</span><strong>'+typeLabel+'</strong>'+fontSummary+'</div><label for="emc-quick-label">表示名</label><input type="text" id="emc-quick-label" class="emcore-input" value="'+safeLabel+'" placeholder="編集画面に表示する名前"><div class="emcore-modal-actions"><button type="button" class="emcore-btn" id="emc-quick-cancel">キャンセル</button><button type="button" class="emcore-btn" id="emc-quick-advanced">詳細設定</button>'+(configured?'<button type="button" class="emcore-btn emcore-btn-primary" id="emc-quick-ok">表示名を更新</button><button type="button" class="emcore-btn emcore-btn-danger" id="emc-quick-remove">編集可能を解除</button>':'<button type="button" class="emcore-btn emcore-btn-primary" id="emc-quick-ok">編集可能にする</button>')+'</div></div>';
  document.body.appendChild(wrap);
  wrap.querySelector('#emc-quick-cancel').onclick=function(){wrap.remove()};
  wrap.querySelector('#emc-quick-advanced').onclick=function(){wrap.remove();emcoreOpenAdvancedMarkDialog(target,applyCb,doc)};
  var remove=wrap.querySelector('#emc-quick-remove');if(remove)remove.onclick=function(){applyCb('__remove','','','item','',null,false,'body',target);wrap.remove()};
  var ok=wrap.querySelector('#emc-quick-ok');if(ok)ok.onclick=function(){
    var location=target.getAttribute('data-em-location')||legacyTarget.getAttribute('data-em-location')||emcoreAutoRegion(target);location=location==='header'||location==='footer'?location:'body';
    var scope=target.getAttribute('data-em-scope')||legacyTarget.getAttribute('data-em-scope')||(location==='body'?'item':'site');
    var labelInput=wrap.querySelector('#emc-quick-label');
    var quickLabel=(labelInput?labelInput.value.trim():'')||label||(dynamic?emcoreDefaultComponentTitle(kind):typeLabel);
    applyCb(kind,existingKey||emcoreInternalKey(doc,kind),quickLabel,scope,'',responsive?responsive.variants:null,emcoreSupportsFont(kind),location,target);wrap.remove();
  };
}

function emcoreOpenAdvancedMarkDialog(el, applyCb, doc){
	var detected=emcoreDetectComponent(el);
  var responsive=emcoreResponsivePictureData(el);
  var picture=responsive?responsive.target:((el.tagName&&el.tagName.toLowerCase()==='picture')?el:(el.closest?el.closest('picture'):null));
  var legacyTarget=responsive&&responsive.preview?responsive.preview:el;
  var kind=el.getAttribute('data-em-editable')||legacyTarget.getAttribute('data-em-editable')||(detected?detected.kind:emcoreGuessKind(el));
  // 未設定の要素には推測キーを入れない。保存済みのキーだけを表示する。
  // 種類名（text/image/link など）が意図せず重複キーとして保存されるのを防ぐ。
  var key=el.getAttribute('data-em-key')||legacyTarget.getAttribute('data-em-key')||'';
  var label=el.getAttribute('data-em-label')||legacyTarget.getAttribute('data-em-label')||'';
	var fontEditable=el.getAttribute('data-em-font-editable')==='1'||legacyTarget.getAttribute('data-em-font-editable')==='1';
  var location=el.getAttribute('data-em-location')||legacyTarget.getAttribute('data-em-location')||emcoreAutoRegion(el)||'body';
  var previewUrl=emcoreExtractUrlFromEl(responsive&&responsive.preview?responsive.preview:el);
  var sources=[];
  if(picture){
    picture.querySelectorAll('source[media]').forEach(function(s){
      sources.push({media:s.getAttribute('media')||'', src:s.getAttribute('srcset')||s.getAttribute('src')||''});
    });
  }
  var used=emcoreUsedKeys(doc|| (el.ownerDocument));
  var wrap=document.createElement('div');
  wrap.className='emcore-modal';
  var previewHtml='';
  if(previewUrl){
    previewHtml='<div class="emcore-modal-preview"><img src="'+previewUrl.replace(/"/g,'')+'" alt="選択中の画像"></div>';
  }
  var chips=used.map(function(k){
    return '<button type="button" class="emcore-chip" data-key="'+k+'">'+k+'</button>';
  }).join('');
  wrap.innerHTML='<div class="emcore-modal-card">'+
    '<h3>この場所は何ですか？</h3>'+
    '<p class="emcore-help emcore-element-path">'+emcoreElementPath(el)+'</p>'+
	(detected?'<p class="emcore-detect-notice">'+detected.message+'。画像1枚ではなく、まとまり全体を編集対象にします。</p>':'')+
    '<div class="emcore-modal-nav"><button type="button" class="emcore-btn emcore-btn-sm" id="emc-parent">親を選択</button> <button type="button" class="emcore-btn emcore-btn-sm" id="emc-child">子を選択</button></div>'+
    previewHtml+
    '<label>種類</label><select id="emc-kind">'+
    '<option value="text">文字</option><option value="image">画像</option>'+
    '<option value="bg">背景画像</option><option value="href">リンク</option>'+
		'<option value="slides">スライダー（枚数の増減）</option><option value="accordion">FAQ／アコーディオン（項目の増減）</option><option value="menu">メニュー（項目の増減）</option><option value="list">リスト</option><option value="steps">ステップ／タイムライン</option><option value="gallery">ギャラリー</option><option value="tabs">タブ</option><option value="table">表／比較表</option><option value="repeater">カード一覧</option><option value="form">お問い合わせフォーム</option><option value="repeat">投稿一覧カード</option></select>'+
    (sources.length?('<div class="emcore-shared-box"><p class="emcore-help"><strong>この画像は画面幅で切り替わります</strong></p>'+sources.map(function(s,i){return '<label>'+s.media+'</label><input type="text" class="emcore-input emc-src" data-media="'+s.media+'" value="'+(s.src||'')+'">';}).join('')+'<label>それ以外（img）</label><input type="text" class="emcore-input" id="emc-src-default" value="'+(previewUrl||'')+'"></div>'):'')+
    '<label>キー（英数字）</label>'+
    '<input type="text" id="emc-key" class="emcore-input" placeholder="キーを設定してください">'+
    '<p class="emcore-help" style="margin:6px 0 0">同じキーの場所は同じ内容になります。新しい内容なら別名を付けてください。</p>'+
    (chips?('<div class="emcore-chips"><span class="emcore-help">このテンプレートで使用中:</span> '+chips+'</div>'):'<p class="emcore-help">まだ他のキーはありません。</p>')+
    '<label>編集先</label><select id="emc-location">'+
    '<option value="body">ページコンテンツ</option><option value="header">共通ヘッダー</option><option value="footer">共通フッター</option></select>'+
    '<p class="emcore-help">Header／Footerを選ぶと、サイドメニューの共通編集画面に表示されます。</p>'+
    '<label>適用範囲</label><select id="emc-scope">'+
    '<option value="item">各ページ／各投稿で変える</option>'+
    '<option value="template">このテンプレートで共通</option>'+
    '<option value="site">サイト全体で共通（ヘッダー等）</option></select>'+
    '<div id="emc-shared-box" class="emcore-shared-box" style="display:none">'+
    '<p class="emcore-help" style="margin:0 0 8px"><strong>ここでリンク先を決めると、各投稿では設定しません。</strong></p>'+
    '<label>固定ページから選ぶ</label>'+
    '<select id="emc-page" class="emcore-select"><option value="">選択してください</option></select>'+
    '<label>またはURLを入力</label>'+
    '<input type="url" id="emc-shared-url" class="emcore-input" placeholder="https://">'+
    '</div>'+
    '<label>表示名（クライアント画面用・任意）</label>'+
    '<input type="text" id="emc-label" class="emcore-input" placeholder="例: 立ち絵、サブ写真">'+
	'<div id="emc-font-option" class="emcore-shared-box"><label class="emcore-check"><input type="checkbox" id="emc-font-editable"> フォントの変更を許可</label><p class="emcore-help">WordPressに登録・追加されたフォントを内容編集画面で選べるようにします。</p></div>'+
    '<div class="emcore-modal-actions"><button type="button" class="emcore-btn emcore-btn-primary" id="emc-ok">設定</button> '+
    ((el.hasAttribute('data-em-editable')||el.hasAttribute('data-em-repeat'))?'<button type="button" class="emcore-btn emcore-btn-danger" id="emc-remove">マークを解除</button> ':'')+
    '<button type="button" class="emcore-btn" id="emc-cancel">キャンセル</button></div></div>';
  document.body.appendChild(wrap);
	wrap.querySelector('#emc-kind').value=['text','image','bg','href','slides','accordion','menu','list','steps','gallery','tabs','table','repeater','form','repeat'].indexOf(kind)>=0?kind:'text';
  wrap.querySelector('#emc-key').value=key||'';
  wrap.querySelector('#emc-label').value=label||'';
	wrap.querySelector('#emc-location').value=['header','footer'].indexOf(location)>=0?location:'body';
	wrap.querySelector('#emc-font-editable').checked=fontEditable||emcoreSupportsFont(kind);
  var scope=el.getAttribute('data-em-scope')||legacyTarget.getAttribute('data-em-scope')||'item';
  wrap.querySelector('#emc-scope').value=scope;
  var $pg=wrap.querySelector('#emc-page');
  (emcoreAdmin.pages||[]).forEach(function(pg){
    var o=document.createElement('option');
    o.value=pg.url; o.textContent=pg.title; $pg.appendChild(o);
  });
  function syncSharedBox(){
    var loc=wrap.querySelector('#emc-location').value;
    if(loc==='header'||loc==='footer')wrap.querySelector('#emc-scope').value='site';
    var sc=wrap.querySelector('#emc-scope').value;
    wrap.querySelector('#emc-shared-box').style.display=(sc!=='item')?'block':'none';
	wrap.querySelector('#emc-font-option').style.display=emcoreSupportsFont(wrap.querySelector('#emc-kind').value)?'block':'none';
  }
  var currentHref='';
  if(el.closest){
    var a0=el.closest('a');
    if(a0) currentHref=a0.getAttribute('href')||'';
  }
  if(currentHref && currentHref.indexOf('http')===0){
    wrap.querySelector('#emc-shared-url').value=currentHref;
  }
  wrap.querySelector('#emc-scope').addEventListener('change',syncSharedBox);
  wrap.querySelector('#emc-location').addEventListener('change',syncSharedBox);
  wrap.querySelector('#emc-kind').addEventListener('change',syncSharedBox);
  $pg.addEventListener('change',function(){ if($pg.value) wrap.querySelector('#emc-shared-url').value=$pg.value; });
  syncSharedBox();
  window.emcoreSharedDraft=window.emcoreSharedDraft||{tpl:{},site:{}};
  wrap.querySelectorAll('.emcore-chip').forEach(function(btn){
    btn.addEventListener('click',function(){
      wrap.querySelector('#emc-key').value=btn.getAttribute('data-key');
    });
  });
  wrap.querySelector('#emc-cancel').onclick=function(){ wrap.remove(); };
  wrap.querySelector('#emc-parent').onclick=function(){var next=el.parentElement;if(next&&next.tagName.toLowerCase()!=='body'){wrap.remove();emcoreOpenAdvancedMarkDialog(next,applyCb,doc)}};
  wrap.querySelector('#emc-child').onclick=function(){var next=el.firstElementChild;if(next){wrap.remove();emcoreOpenAdvancedMarkDialog(next,applyCb,doc)}};
  var removeBtn=wrap.querySelector('#emc-remove');if(removeBtn)removeBtn.onclick=function(){applyCb('__remove','','','item','',null,false,'body',picture||el);wrap.remove()};
  wrap.querySelector('#emc-ok').onclick=function(){
    var keyInput=wrap.querySelector('#emc-key');
    var rawKey=(keyInput.value||'').trim();
    var k=rawKey.replace(/[^a-z0-9_-]/gi,'').toLowerCase();
    if(!k){
      keyInput.setCustomValidity('キーを設定してください。');
      keyInput.reportValidity();
      keyInput.focus();
      return;
    }
    keyInput.setCustomValidity('');
    var lb=wrap.querySelector('#emc-label').value.trim();
    var sc=wrap.querySelector('#emc-scope').value||'item';
    var sharedUrl=(wrap.querySelector('#emc-shared-url').value||'').trim();
    var variants=null;
    var srcs=wrap.querySelectorAll('.emc-src');
    if(srcs.length){
      variants={default:(wrap.querySelector('#emc-src-default')||{}).value||previewUrl||'',sources:{}};
      srcs.forEach(function(inp){ variants.sources[inp.getAttribute('data-media')]=inp.value; });
    }
	var allowFont=emcoreSupportsFont(wrap.querySelector('#emc-kind').value) && wrap.querySelector('#emc-font-editable').checked;
    applyCb(wrap.querySelector('#emc-kind').value, k, lb, sc, sharedUrl, variants, allowFont, wrap.querySelector('#emc-location').value,(variants&&picture)?picture:el);
    wrap.remove();
  };
}

function emcorePreparePreviewHtml(html){
  var parsed=new DOMParser().parseFromString(String(html||''),'text/html');
  parsed.querySelectorAll('script').forEach(function(n){var type=n.getAttribute('type');if(type)n.setAttribute('data-emcore-script-type',type);n.setAttribute('type','text/emcore-disabled')});
  parsed.querySelectorAll('*').forEach(function(n){Array.from(n.attributes||[]).forEach(function(a){if(/^on[a-z]+$/i.test(a.name)){n.setAttribute('data-emcore-'+a.name,a.value);n.removeAttribute(a.name)}})});
  return '<!DOCTYPE html>\n'+parsed.documentElement.outerHTML;
}

function emcoreInitPreview(){
  if(!window.emcoreTplEdit) return;
  var iframe=document.getElementById('emcore-preview');
  if(!iframe) return;
  var markOn=false;
	$('#emcore-view-toggle').on('click',function(){
	  var normal=document.body.classList.toggle('emcore-editor-normal');
	  $(this).text(normal?'フルスクリーン':'通常表示');
  });
  var undoStack=[],redoStack=[];
  var hasUnsavedChanges=false,allowNavigation=false;
  function setDirty(dirty){
    hasUnsavedChanges=!!dirty;
    document.body.classList.toggle('emcore-has-unsaved-changes',hasUnsavedChanges);
  }
  function syncUndo(){ $('#emcore-mark-undo').prop('disabled',!undoStack.length);$('#emcore-mark-redo').prop('disabled',!redoStack.length); }
  function snapshot(doc){undoStack.push(doc.documentElement.outerHTML);if(undoStack.length>30)undoStack.shift();redoStack=[];setDirty(true);syncUndo();}
  function writeHtml(html){
		html=emcorePreparePreviewHtml(html).replace(/^\s+/, '');
    var doc=iframe.contentDocument;
    doc.open();
    doc.write(html);
    doc.close();
    if(doc.body){
      var first=doc.body.firstChild;
      if(first && first.nodeType===3 && /^[\s\\n]+$/.test(first.nodeValue||'')){
        first.parentNode.removeChild(first);
      }
    }
    var st=doc.createElement('style');
    st.textContent='[data-em-editable]{outline:2px dashed #ff6b9a!important;outline-offset:2px} .emcore-layer-focus{outline:3px solid #3ea6ff!important}';
    doc.head.appendChild(st);
    bindDoc(doc);
    renderLayers(doc);
  }
  function renderLayers(doc){
	var $tree=$('#emcore-layer-list').empty(), layers=emcoreCollectLayers(doc), groups={};
	layers.forEach(function(L,i){L.uid='eml-'+i;L.el.setAttribute('data-emcore-layer-id',L.uid);(groups[L.region]||(groups[L.region]=[])).push(L)});
	Object.keys(groups).forEach(function(region){
	  var $group=$('<details class="emcore-layer-group" open></details>'),$summary=$('<summary></summary>').text(region+' ');
	  $summary.append($('<span></span>').text(groups[region].length));$group.append($summary);var $list=$('<ul class="emcore-layer-list"></ul>');
	  groups[region].forEach(function(L){
		var category=(L.type==='img'||L.type==='bg')?'image':(L.type==='link'?'link':'text');
		var $li=$('<li class="emcore-layer-item"></li>').attr({'data-layer-id':L.uid,'data-category':category,'data-marked':L.marked?'1':'0','data-search':((L.text||'')+' '+(L.href||'')+' '+(L.key||'')+' '+L.name).toLowerCase()});
		if(category==='image'&&L.url)$li.append($('<img class="emcore-layer-thumb" alt="">').attr('src',L.url));else $li.append($('<span class="emcore-layer-icon"></span>').text(category==='link'?'↗':'T'));
		var $info=$('<span class="emcore-layer-info"></span>'), title=L.text||L.name;
		$info.append($('<strong></strong>').text(title));
		if(L.href)$info.append($('<small></small>').text(L.href));else if(L.url)$info.append($('<small></small>').text(emcoreFileNameFromUrl(L.url)));else $info.append($('<small></small>').text(L.tag.toUpperCase()));
		$li.append($info);
		if(L.key)$li.append($('<code class="emcore-layer-key"></code>').text(L.key));
		if(L.marked)$li.append($('<span class="emcore-layer-badge is-editable"></span>').text('編集可'));
		if(L.scope==='site')$li.append($('<span class="emcore-layer-badge"></span>').text('共通'));
		$li.on('mouseenter',function(){L.el.classList.add('emcore-layer-focus');if(L.url){$('#emcore-layer-popover').html($('<img alt="画像プレビュー">').attr('src',L.url)).prop('hidden',false)}});
		$li.on('mouseleave',function(){doc.querySelectorAll('.emcore-layer-focus').forEach(function(n){n.classList.remove('emcore-layer-focus')});$('#emcore-layer-popover').prop('hidden',true).empty()});
		$li.on('click',function(){try{L.el.scrollIntoView({behavior:'smooth',block:'center'})}catch(e){}if(markOn)openFor(L.el)});
		$list.append($li);
	  });$group.append($list);$tree.append($group);
	});
	(window.emcoreTplEdit.otherTemplates||[]).forEach(function(tpl){var parsed=new DOMParser().parseFromString(String(tpl.html||''),'text/html'),marked=emcoreCollectLayers(parsed).filter(function(L){return !!L.key});if(!marked.length)return;var $group=$('<details class="emcore-layer-group emcore-other-template" hidden></details>'),$summary=$('<summary></summary>').text('PAGE · '+String(tpl.name||'').toUpperCase()+' ');$summary.append($('<span></span>').text(marked.length));var $list=$('<ul class="emcore-layer-list"></ul>');marked.forEach(function(L){var category=(L.type==='img'||L.type==='bg')?'image':(L.type==='link'?'link':'text'),$li=$('<li class="emcore-layer-item is-external"></li>').attr({'data-category':category,'data-marked':'1','data-key':L.key,'data-search':((L.text||'')+' '+(L.href||'')+' '+L.key+' '+tpl.name).toLowerCase()}),$info=$('<span class="emcore-layer-info"></span>');if(category==='image'&&L.url)$li.append($('<img class="emcore-layer-thumb" alt="">').attr('src',L.url));else $li.append($('<span class="emcore-layer-icon"></span>').text(category==='link'?'↗':'T'));$info.append($('<strong></strong>').text(L.text||L.name)).append($('<small></small>').text(tpl.name));$li.append($info).append($('<code class="emcore-layer-key"></code>').text(L.key));$list.append($li)});$group.append($summary).append($list);$tree.append($group)});
	var keys=[];$('#emcore-layer-list .emcore-layer-key').each(function(){var k=$(this).text();if(k&&keys.indexOf(k)<0)keys.push(k)});keys.sort();var $kf=$('#emcore-layer-key-filter').empty().append('<option value="">すべてのキー</option>');keys.forEach(function(k){$kf.append($('<option></option>').val(k).text(k))});
	$('#emcore-layer-count').text(layers.length+' ITEMS');emcoreApplyLayerFilter();renderComponents(doc);
  }
	function emcoreApplyLayerFilter(){
	  var q=String($('#emcore-layer-search').val()||'').toLowerCase(),filter=$('#emcore-layer-filters .is-on').data('filter')||'all',key=$('#emcore-layer-key-filter').val()||'',showOther=$('#emcore-layer-all-templates').is(':checked');
	  $('#emcore-layer-list .emcore-other-template').prop('hidden',!showOther);
	  $('#emcore-layer-list .emcore-layer-item').each(function(){var $x=$(this),external=$x.closest('.emcore-other-template').length>0,ok=(!external||showOther)&&(!q||String($x.data('search')).indexOf(q)>=0)&&(!key||String($x.data('key')||$x.find('.emcore-layer-key').text())===key)&&(filter==='all'||(filter==='marked'?$x.data('marked')===1:$x.data('category')===filter));$x.toggle(ok)});
	  $('#emcore-layer-list .emcore-layer-group').each(function(){$(this).toggle($(this).find('.emcore-layer-item:visible').length>0)});
	}
	$('#emcore-layer-search').on('input',emcoreApplyLayerFilter);
	$('#emcore-layer-key-filter,#emcore-layer-all-templates').on('change',emcoreApplyLayerFilter);
	$('#emcore-layer-filters').on('click','button',function(){$(this).addClass('is-on').siblings().removeClass('is-on');emcoreApplyLayerFilter()});
  function emcorePreferMarkTarget(el){
    if(!el||!el.tagName) return el;
    var kind=emcoreGuessKind(el);
    if(kind!=='text') return el;
    var n=el;
    for(var i=0;i<8 && n && n.tagName;i++){
      try{
        var cs=n.ownerDocument.defaultView.getComputedStyle(n);
        var wm=(cs.writingMode||cs.webkitWritingMode||'');
        if(String(wm).indexOf('vertical')===0 && !(n.querySelector&&n.querySelector('img'))) return n;
      }catch(e){}
      n=n.parentElement;
    }
    if(el.querySelector && el.querySelector('img')){
      var cand=el.querySelector('[style*="writing-mode"],[style*="writingMode"]');
      if(cand) return cand;
    }
    return el;
  }
  function openFor(el){
    el=emcorePreferMarkTarget(el);
    emcoreOpenQuickMarkDialog(el,function(kind,key,label,scope,sharedUrl,variants,allowFont,location,targetOverride){
      snapshot(iframe.contentDocument);
      var target=targetOverride||el;
      if(kind==='__remove'){
		['data-em-editable','data-em-key','data-em-scope','data-em-location','data-em-label','data-em-responsive','data-em-variants','data-em-repeat','data-em-font-editable','data-em-component'].forEach(function(a){target.removeAttribute(a)});
		target.querySelectorAll('[data-em-accordion-item],[data-em-accordion-question],[data-em-accordion-answer]').forEach(function(n){n.removeAttribute('data-em-accordion-item');n.removeAttribute('data-em-accordion-question');n.removeAttribute('data-em-accordion-answer')});
		target.querySelectorAll('[data-em-menu-item],[data-em-menu-label]').forEach(function(n){n.removeAttribute('data-em-menu-item');n.removeAttribute('data-em-menu-label')});
		target.querySelectorAll('[data-em-list-item],[data-em-list-text],[data-em-step-item],[data-em-step-index],[data-em-step-title],[data-em-step-description],[data-em-gallery-item],[data-em-gallery-image],[data-em-gallery-link],[data-em-tab-item],[data-em-tab-trigger],[data-em-tab-content],[data-em-table-row],[data-em-table-cell],[data-em-repeater-item],[data-em-repeater-field]').forEach(function(n){Array.from(n.attributes).forEach(function(a){if(a.name.indexOf('data-em-list')===0||a.name.indexOf('data-em-step')===0||a.name.indexOf('data-em-gallery')===0||a.name.indexOf('data-em-tab')===0||a.name.indexOf('data-em-table')===0||a.name.indexOf('data-em-repeater')===0)n.removeAttribute(a.name)})});
        $('#emcore-mark-status').text('マークを解除しました（未保存）');renderLayers(iframe.contentDocument);return;
      }
      if(kind==='href'){
        var a=el.closest?el.closest('a'):null;
        if(a) target=a;
      }
      if(kind==='slides'){
		var detectedSlider=emcoreDetectComponent(el);
		var wrap=detectedSlider&&detectedSlider.kind==='slides'?detectedSlider.target:(el.closest? (el.closest('.swiper-wrapper')||el.closest('.slider')||el.closest('.slides')||el.closest('[class*="swiper"]')):null);
        if(wrap) target=wrap;
        else if(el.parentElement && el.parentElement.children.length>1) target=el.parentElement;
		target=emcoreConfigureComponent(target,'slides');
      }
	  if(kind==='accordion'){
		var detectedAccordion=emcoreDetectComponent(el);
		if(detectedAccordion&&detectedAccordion.kind==='accordion')target=detectedAccordion.target;
		target=emcoreConfigureComponent(target,'accordion');
	  }
	  if(kind==='menu'){
		var detectedMenu=emcoreDetectComponent(el);
		if(detectedMenu&&detectedMenu.kind==='menu')target=detectedMenu.target;
		target=emcoreConfigureComponent(target,'menu');
	  }
	  if(['list','steps','gallery','tabs','table','repeater'].indexOf(kind)!==-1){var detectedCollection=emcoreDetectComponent(el);if(detectedCollection&&detectedCollection.kind===kind)target=detectedCollection.target;target=emcoreConfigureComponent(target,kind)}
      if(kind==='form'){
        var detectedForm=emcoreDetectComponent(el);
        if(detectedForm&&detectedForm.kind==='form')target=detectedForm.target;
        target=emcoreConfigureComponent(target,'form');
        target.removeAttribute('data-em-editable');
        target.setAttribute('data-em-key',key);
        target.setAttribute('data-em-label',label||'お問い合わせフォーム');
        target.removeAttribute('data-em-scope');
        $('#emcore-mark-status').text('フォームを指定しました（未保存）');renderLayers(iframe.contentDocument);return;
      }
      if(kind==='repeat'){
        target.setAttribute('data-em-repeat','posts');
		target.setAttribute('data-em-component','repeater');target.setAttribute('data-em-key',key);target.setAttribute('data-em-label',label||'一覧コンテンツ');target.removeAttribute('data-em-editable');target.removeAttribute('data-em-scope');
        $('#emcore-mark-status').text('一覧カードを指定しました（未保存）');renderLayers(iframe.contentDocument);return;
      }
      target.setAttribute('data-em-editable', kind);
      target.setAttribute('data-em-key', key);
      target.setAttribute('data-em-scope', scope||'item');
      target.setAttribute('data-em-location', location||'body');
      if(label) target.setAttribute('data-em-label', label);
      else target.removeAttribute('data-em-label');
	  if(emcoreSupportsFont(kind) && allowFont) target.setAttribute('data-em-font-editable','1');
	  else target.removeAttribute('data-em-font-editable');
      window.emcoreSharedDraft=window.emcoreSharedDraft||{tpl:{},site:{}};
      if((scope==='template'||scope==='site') && sharedUrl){
        if(scope==='site') window.emcoreSharedDraft.site[key]=sharedUrl;
        else window.emcoreSharedDraft.tpl[key]=sharedUrl;
      }
      if(variants){
        target.setAttribute('data-em-responsive','1');
        if(scope==='template') window.emcoreSharedDraft.tpl[key]=JSON.stringify(variants);
        else if(scope==='site') window.emcoreSharedDraft.site[key]=JSON.stringify(variants);
        else target.setAttribute('data-em-variants',JSON.stringify(variants));
      }else{
        target.removeAttribute('data-em-responsive');
        target.removeAttribute('data-em-variants');
      }
      if(target!==el){
        ['data-em-editable','data-em-key','data-em-scope','data-em-location','data-em-label','data-em-responsive','data-em-variants','data-em-font-editable'].forEach(function(a){el.removeAttribute(a)});
      }
      $('#emcore-mark-status').text('未保存のマークがあります');
      renderLayers(iframe.contentDocument);
    }, iframe.contentDocument);
  }
  function renderComponents(doc){
    var candidates=emcoreAutoDetect(doc),$list=$('#emcore-component-list').empty();
    $('#emcore-component-count').text(candidates.length+'件');
    if(!candidates.length){$list.append('<p class="emcore-muted">専用マーカー付きの動的コンテンツはありません。通常の文字・画像はマークモードから選択できます。</p>');return;}
    candidates.forEach(function(c){
		var names={slides:'スライダー',accordion:'アコーディオン',menu:'メニュー',list:'リスト',steps:'ステップ',gallery:'ギャラリー',tabs:'タブ',table:'表',repeater:'カード一覧',form:'フォーム',repeat:'投稿一覧'};
      var count=0;if(c.kind==='slides')count=c.el.querySelectorAll('.emcore-slider-item,.swiper-slide,.splide__slide,.slick-slide,.slider-slide,.slide').length;
      if(c.kind==='accordion')count=c.el.querySelectorAll('.emcore-accordion-item,[data-em-accordion-item],details,.faq-item,.accordion-item').length;
		if(c.kind==='repeat')count=c.el.querySelectorAll('.emcore-repeater-item,[data-em-repeat-item]').length;
		if(c.kind==='menu')count=c.el.querySelectorAll('.emcore-menu-item,[data-em-menu-item="1"],a[href]').length;
		if(c.kind==='list')count=c.el.querySelectorAll('.emcore-list-item,[data-em-list-item="1"]').length;
		if(c.kind==='steps')count=c.el.querySelectorAll('.emcore-step-item,[data-em-step-item="1"]').length;
		if(c.kind==='gallery')count=c.el.querySelectorAll('.emcore-gallery-item,[data-em-gallery-item="1"]').length;
		if(c.kind==='tabs')count=c.el.querySelectorAll('.emcore-tab-item,[data-em-tab-item="1"]').length;
		if(c.kind==='table')count=c.el.querySelectorAll('.emcore-table-row,[data-em-table-row="1"]').length;
		if(c.kind==='repeater')count=c.el.querySelectorAll('.emcore-repeater-item,[data-em-repeater-item="1"]').length;
      var $row=$('<div class="emcore-component-row"></div>'),$info=$('<div></div>');
      $info.append($('<strong></strong>').text(c.el.getAttribute('data-em-label')||names[c.kind]||c.label));
		var targetCount=c.targets&&c.targets.length?c.targets.length:1;
		$info.append($('<span></span>').text((names[c.kind]||c.kind)+(count?' · '+count+'項目':'')+(targetCount>1?' · 表示先'+targetCount+'か所':'')+(c.configured?' · 設定済み':' · 未設定')));
      var $btn=$('<button type="button" class="emcore-btn emcore-btn-sm"></button>').text(c.configured?'設定を確認':'編集可能にする');
      $btn.on('click',function(){openFor(c.el)});$row.append($info).append($btn);$list.append($row);
    });
  }
  function bindDoc(doc){
    function blockNav(e){
      e.preventDefault();
      e.stopPropagation();
      if(e.stopImmediatePropagation) e.stopImmediatePropagation();
    }
    ['click','auxclick','dblclick'].forEach(function(ev){
      doc.addEventListener(ev,function(e){
        blockNav(e);
        if(markOn && ev==='click') openFor(e.target);
      }, true);
    });
    doc.addEventListener('submit', blockNav, true);
	doc.addEventListener('mouseover',function(e){var el=e.target&&e.target.closest?e.target.closest('[data-emcore-layer-id]'):null;if(!el)return;var row=document.querySelector('[data-layer-id="'+el.getAttribute('data-emcore-layer-id')+'"]');if(row){document.querySelectorAll('.emcore-layer-item.is-preview-hover').forEach(function(n){n.classList.remove('is-preview-hover')});row.classList.add('is-preview-hover');row.scrollIntoView({block:'nearest'})}},true);
	doc.addEventListener('mouseout',function(){document.querySelectorAll('.emcore-layer-item.is-preview-hover').forEach(function(n){n.classList.remove('is-preview-hover')})},true);
    try{
      doc.querySelectorAll('a[href]').forEach(function(a){
        a.addEventListener('click', blockNav, true);
      });
    }catch(err){}
  }
  $('#emcore-bp').on('click','button',function(){
    var w=$(this).data('w');
    $('#emcore-bp button').removeClass('is-on');
    $(this).addClass('is-on');
    var $fr=$('#emcore-preview');
    $fr.css({width:w+'px',maxWidth:'100%',margin:'0 auto',display:'block'});
  });
	(function(){var split=document.getElementById('emcore-editor-splitter'),layout=split&&split.parentElement;if(!split||!layout)return;function set(x){var r=layout.getBoundingClientRect(),pct=Math.max(38,Math.min(78,((x-r.left)/r.width)*100));layout.style.setProperty('--emcore-preview-width',pct+'%')}split.addEventListener('pointerdown',function(e){split.setPointerCapture(e.pointerId);split.classList.add('is-active')});split.addEventListener('pointermove',function(e){if(split.hasPointerCapture(e.pointerId))set(e.clientX)});split.addEventListener('pointerup',function(e){if(split.hasPointerCapture(e.pointerId))split.releasePointerCapture(e.pointerId);split.classList.remove('is-active')});split.addEventListener('dblclick',function(){layout.style.removeProperty('--emcore-preview-width')});split.addEventListener('keydown',function(e){if(e.key!=='ArrowLeft'&&e.key!=='ArrowRight')return;var current=parseFloat(getComputedStyle(layout).getPropertyValue('--emcore-preview-width'))||60;layout.style.setProperty('--emcore-preview-width',Math.max(38,Math.min(78,current+(e.key==='ArrowRight'?2:-2)))+'%')})})();
  $('#emcore-mark-toggle').on('click',function(){
    markOn=!markOn;
    $(this).text('マークモード: '+(markOn?'ON':'OFF'));
  });
  $('#emcore-auto-detect').on('click',function(){
    var doc=iframe.contentDocument,candidates=emcoreAutoDetect(doc);
    renderComponents(doc);$('#emcore-dynamic-components').prop('open',true);
    $('#emcore-mark-status').text(candidates.length?candidates.length+'件の動的コンテンツを検出しました':'専用マーカー付きの動的コンテンツはありません');
  });
  $('#emcore-mark-reload').on('click',async function(){
    if(hasUnsavedChanges&&!await emcoreConfirm('保存していない変更は失われます。',{kicker:'EMERGE MONO / UNSAVED CHANGES',title:'変更を破棄して再読込しますか？',confirmLabel:'破棄して再読込',cancelLabel:'編集を続ける',danger:true}))return;
    setDirty(false);undoStack=[];redoStack=[];syncUndo();writeHtml(window.emcoreTplEdit.previewHtml||window.emcoreTplEdit.html);
  });
  $('#emcore-mark-undo').on('click',function(){if(!undoStack.length)return;var doc=iframe.contentDocument;redoStack.push(doc.documentElement.outerHTML);writeHtml(undoStack.pop());syncUndo();});
  $('#emcore-mark-redo').on('click',function(){if(!redoStack.length)return;var doc=iframe.contentDocument;undoStack.push(doc.documentElement.outerHTML);writeHtml(redoStack.pop());syncUndo();});
  function restorePreviewSafety(root){
    root.querySelectorAll('script[type="text/emcore-disabled"]').forEach(function(n){var t=n.getAttribute('data-emcore-script-type');if(t)n.setAttribute('type',t);else n.removeAttribute('type');n.removeAttribute('data-emcore-script-type')});
    root.querySelectorAll('[data-emcore-src]').forEach(function(n){n.setAttribute('src',n.getAttribute('data-emcore-src'));n.removeAttribute('data-emcore-src')});
    root.querySelectorAll('[data-emcore-object-data]').forEach(function(n){n.setAttribute('data',n.getAttribute('data-emcore-object-data'));n.removeAttribute('data-emcore-object-data')});
    root.querySelectorAll('*').forEach(function(n){Array.from(n.attributes||[]).forEach(function(a){if(a.name.indexOf('data-emcore-on')===0){n.setAttribute(a.name.replace('data-emcore-',''),a.value);n.removeAttribute(a.name)}})});
    root.querySelectorAll('meta[data-emcore-http-equiv]').forEach(function(n){n.setAttribute('http-equiv',n.getAttribute('data-emcore-http-equiv'));n.removeAttribute('data-emcore-http-equiv')});
  }
  $('#emcore-mark-save').on('click',function(){
    var doc=iframe.contentDocument;
    var cloned=doc.documentElement.cloneNode(true);
    cloned.querySelectorAll('.emcore-layer-focus').forEach(function(n){ n.classList.remove('emcore-layer-focus'); });
	cloned.querySelectorAll('[data-emcore-layer-id]').forEach(function(n){n.removeAttribute('data-emcore-layer-id')});
    cloned.querySelectorAll('style').forEach(function(s){
      if(/data-em-editable|emcore-layer-focus/.test(s.textContent||'')) s.remove();
    });
    restorePreviewSafety(cloned);
    var html='<!DOCTYPE html>\n'+cloned.outerHTML;
    html=html.replace(/\[data-em-editable\]\{[^}]*\}/g,'');
    html=html.replace(/\.emcore-layer-focus\{[^}]*\}/g,'');
    var draft=window.emcoreSharedDraft||{tpl:{},site:{}};
    post('emcore_save_template',{id:window.emcoreTplEdit.id,name:window.emcoreTplEdit.name,type:window.emcoreTplEdit.type,html:html,shared:JSON.stringify(draft.tpl||{}),site_shared:JSON.stringify(draft.site||{})}).done(function(r){
      $('#emcore-mark-status').text(r.success?'保存しました':'保存に失敗');
      if(r.success){setDirty(false);allowNavigation=true;setTimeout(function(){ location.reload(); }, 500);}
    });
  });
  window.addEventListener('beforeunload',function(e){
    if(!hasUnsavedChanges||allowNavigation)return;
    e.preventDefault();
    e.returnValue='';
  });
  document.addEventListener('click',function(e){
    if(!hasUnsavedChanges||allowNavigation||e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey)return;
    var link=e.target&&e.target.closest?e.target.closest('a[href]'):null;
    if(!link||link.hasAttribute('download')||String(link.getAttribute('target')||'').toLowerCase()==='_blank')return;
    var raw=String(link.getAttribute('href')||'');
    if(!raw||raw.charAt(0)==='#'||/^javascript:/i.test(raw))return;
    var next;
    try{next=new URL(link.href,window.location.href);}catch(err){return;}
    if(next.href===window.location.href)return;
    e.preventDefault();
    e.stopImmediatePropagation();
    emcoreConfirm('このページには保存していない変更があります。移動すると変更内容は失われます。',{kicker:'EMERGE MONO / UNSAVED CHANGES',title:'保存せずに移動しますか？',confirmLabel:'保存せずに移動',cancelLabel:'編集を続ける',danger:true}).then(function(ok){
      if(!ok)return;
      allowNavigation=true;
      window.location.assign(next.href);
    });
  },true);
  writeHtml(window.emcoreTplEdit.previewHtml||window.emcoreTplEdit.html);
}
$(document).on('click','.emcore-pick-shared-image',function(){
  var input=$(this).siblings('.emcore-shared-val').get(0);
  var frame=wp.media({title:'画像を選択',button:{text:'この画像を使用'},multiple:false});
  frame.on('select',function(){var item=frame.state().get('selection').first().toJSON();if(input)input.value=item.url||''});
  frame.open();
});
$(document).on('click','#emcore-save-shared-content',function(){
  var values={};$('.emcore-shared-val').each(function(){values[$(this).data('key')]=$(this).val()||''});
  var $btn=$(this).prop('disabled',true),$msg=$('#emcore-shared-msg').text('保存中…');
  post('emcore_save_shared_content',{values:JSON.stringify(values)}).done(function(r){$msg.text(r.success?r.data.message:(r.data&&r.data.message)||'保存に失敗しました')}).fail(function(){$msg.text('保存に失敗しました')}).always(function(){$btn.prop('disabled',false)});
});
function emcoreInitWorkspace(){
  var buttons=document.querySelectorAll('[data-emcore-workspace-button]');
  if(!buttons.length)return;
  var activeGroup=document.querySelector('[data-emcore-workspace-group] .is-active');
  var inferred=activeGroup&&activeGroup.closest('[data-emcore-workspace-group]')?activeGroup.closest('[data-emcore-workspace-group]').getAttribute('data-emcore-workspace-group'):'';
  var saved='';
  try{saved=window.localStorage.getItem('emcoreWorkspace')||''}catch(e){}
  var initial=inferred||(saved==='manage'||saved==='customize'?saved:'customize');
  function setWorkspace(value){
    document.body.setAttribute('data-emcore-workspace',value);
    buttons.forEach(function(button){
      var active=button.getAttribute('data-emcore-workspace-button')===value;
      button.classList.toggle('is-active',active);
      button.setAttribute('aria-pressed',active?'true':'false');
    });
    document.querySelectorAll('[data-emcore-workspace-group]').forEach(function(group){group.hidden=group.getAttribute('data-emcore-workspace-group')!==value});
    document.querySelectorAll('[data-emcore-workspace-content]').forEach(function(content){content.hidden=content.getAttribute('data-emcore-workspace-content')!==value});
    try{window.localStorage.setItem('emcoreWorkspace',value)}catch(e){}
  }
  buttons.forEach(function(button){button.addEventListener('click',function(){setWorkspace(button.getAttribute('data-emcore-workspace-button'))})});
  setWorkspace(initial);
}
$(emcoreInitWorkspace);
function emcoreRenumberSystemFields($list){
  var type=$list.attr('data-form-type');
  $list.find('.emcore-system-field-row').each(function(index){
    $(this).find('[name]').each(function(){this.name=this.name.replace(/form_fields\[[^\]]+\]\[\d+\]/,'form_fields['+type+']['+index+']')});
  });
}
$(document).on('click','.emcore-system-field-add',function(){
  var type=$(this).attr('data-form-type'),$list=$('.emcore-system-fields[data-form-type="'+type+'"]'),index=$list.find('.emcore-system-field-row').length;
  $list.append('<div class="emcore-system-field-row"><input class="emcore-input" name="form_fields['+type+']['+index+'][label]" placeholder="表示名"><input class="emcore-input" name="form_fields['+type+']['+index+'][key]" placeholder="field_key"><select class="emcore-select" name="form_fields['+type+']['+index+'][type]"><option value="text">テキスト</option><option value="email">メール</option><option value="tel">電話番号</option><option value="textarea">複数行</option></select><input class="emcore-input" name="form_fields['+type+']['+index+'][placeholder]" placeholder="プレースホルダー"><label><input type="checkbox" name="form_fields['+type+']['+index+'][required]" value="1"> 必須</label><button type="button" class="emcore-btn emcore-system-field-up">↑</button><button type="button" class="emcore-btn emcore-system-field-down">↓</button><button type="button" class="emcore-btn emcore-system-field-delete">削除</button></div>');
});
$(document).on('click','.emcore-system-field-delete',function(){var $list=$(this).closest('.emcore-system-fields');$(this).closest('.emcore-system-field-row').remove();emcoreRenumberSystemFields($list)});
$(document).on('click','.emcore-system-field-up,.emcore-system-field-down',function(){var $row=$(this).closest('.emcore-system-field-row'),$list=$row.closest('.emcore-system-fields');if($(this).hasClass('emcore-system-field-up'))$row.prev().before($row);else $row.next().after($row);emcoreRenumberSystemFields($list)});
$(emcoreInitPreview);
})(jQuery);
