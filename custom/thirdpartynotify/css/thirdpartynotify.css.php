<?php
header('Content-Type: text/css; charset=UTF-8');
?>
.thirdpartynotify-panel {
	box-sizing: border-box;
	width: 100%;
	margin: 28px 0 0 0;
	padding: 22px 24px;
	border: 1px solid #d8d8d8;
	border-radius: 6px;
	background: #fff;
	box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
}
.thirdpartynotify-header {
	display: flex;
	align-items: center;
	gap: 12px;
}
.thirdpartynotify-header h3 {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
}
.thirdpartynotify-title-icon {
	font-size: 18px;
	color: #444;
}
.thirdpartynotify-separator {
	height: 1px;
	margin: 20px 0;
	background: #ddd;
}
.thirdpartynotify-picker {
	display: grid;
	grid-template-columns: minmax(220px, 1fr) auto;
	gap: 10px;
	align-items: center;
}
.thirdpartynotify-picker select {
	width: 100%;
	min-height: 38px;
}
.thirdpartynotify-help {
	margin: 18px 0 6px;
	color: #444;
}
.thirdpartynotify-selected-box {
	padding: 16px;
	border: 1px solid #d7d4c8;
	border-radius: 6px;
	background: #f8f6ef;
}
.thirdpartynotify-box-title {
	margin-bottom: 14px;
	font-weight: 600;
	letter-spacing: .02em;
	color: #444;
}
.thirdpartynotify-selected-users {
	display: grid;
	gap: 10px;
}
.thirdpartynotify-user-row {
	display: grid;
	grid-template-columns: 42px minmax(0, 1fr) auto;
	gap: 12px;
	align-items: center;
	padding: 12px;
	border: 1px solid #ddd;
	border-radius: 6px;
	background: #fff;
}
.thirdpartynotify-user-row-no-email {
	border-color: #d7a94b;
	background: #fffaf0;
}
.thirdpartynotify-avatar {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 34px;
	height: 34px;
	border-radius: 50%;
	background: #6a5bd7;
	color: #fff;
	font-weight: 700;
	font-size: 13px;
}
.thirdpartynotify-user-text {
	display: grid;
	min-width: 0;
}
.thirdpartynotify-user-text strong,
.thirdpartynotify-user-text span {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.thirdpartynotify-user-text span {
	color: #444;
}
.thirdpartynotify-user-text .thirdpartynotify-email-warning {
	color: #8a5a00;
	font-weight: 600;
}
.thirdpartynotify-remove {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	min-width: 42px;
	white-space: nowrap;
}
.thirdpartynotify-remove-label {
	font-size: 12px;
}
.thirdpartynotify-actions {
	display: flex;
	align-items: center;
	gap: 14px;
}
.thirdpartynotify-status {
	color: #555;
}
.thirdpartynotify-error {
	color: #b00020;
}
.thirdpartynotify-empty {
	padding: 12px;
	color: #666;
	background: #fff;
	border: 1px dashed #ccc;
	border-radius: 6px;
}
.thirdpartynotify-send-event {
	display: inline-flex;
	align-items: center;
	vertical-align: middle;
	line-height: 1;
	padding-top: 0;
	padding-bottom: 0;
	margin: 0 0 0 18px;
	padding-left: 10px;
	padding-right: 10px;
	height: 28px;
}


ul.timeline li .timeline-item .timeline-header-action2,
ul.timeline li .timeline-item .timeline-header {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 6px;
}

ul.timeline li .timeline-item .timeline-header-action2 .thirdpartynotify-send-event,
ul.timeline li .timeline-item .timeline-header .thirdpartynotify-send-event {
	margin-top: 0;
	margin-bottom: 0;
}
@media (max-width: 700px) {
	.thirdpartynotify-panel {
		padding: 16px;
	}
	.thirdpartynotify-picker {
		grid-template-columns: 1fr;
	}
	.thirdpartynotify-user-row {
		grid-template-columns: 36px minmax(0, 1fr) auto;
	}
	.thirdpartynotify-send-event {
		display: block;
		width: fit-content;
		margin: 8px 0 0 0;
	}
}
