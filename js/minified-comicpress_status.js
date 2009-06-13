
var active_filters={};function toggle_filter(id){if(active_filters[id]===undefined){active_filters[id]=false;}
active_filters[id]=!active_filters[id];var any_filters_active=false;for(var key in active_filters){if(active_filters[key]===true){any_filters_active=true;break;}}
if(top.console&&top.console.log){top.console.log(active_filters);}
var rows=document.getElementsByClassName('data-row');for(var i=0,il=rows.length;i<il;++i){if(any_filters_active){var filter_active=false;for(var key in active_filters){if(top.console&&top.console.log){top.console.log(key);}
if(active_filters[key]===true){if(rows[i].hasClassName(key)){filter_active=true;break;}}}
if(top.console&&top.console.log){top.console.log(i+": "+filter_active);}
if(filter_active){Element.show(rows[i]);}else{Element.hide(rows[i]);}}else{Element.show(rows[i]);}}
for(var key in active_filters){var target=$(key);if(active_filters[key]===true){if(!Element.hasClassName(target,'enabled')){Element.addClassName(target,'enabled');}}else{if(Element.hasClassName(target,'enabled')){Element.removeClassName(target,'enabled');}}}}
function setup_status_togglers(){var togglers=document.getElementsByClassName('toggler');for(var i=0;i<togglers.length;++i){Event.observe(togglers[i],'click',function(e){var element=Event.element(e);toggle_filter(element.id);});}}