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
.thirdpartynotify-dialog-overlay {
	position: fixed;
	inset: 0;
	z-index: 100000;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 16px;
	background: rgba(17, 24, 39, .36);
}
.thirdpartynotify-dialog {
	width: min(460px, 100%);
	padding: 20px;
	border: 1px solid #e5e7eb;
	border-radius: 8px;
	background: #fff;
	box-shadow: 0 18px 48px rgba(17, 24, 39, .22);
}
.thirdpartynotify-dialog-title {
	margin-bottom: 16px;
	color: #111827;
	font-size: 16px;
	font-weight: 700;
}
.thirdpartynotify-dialog-list {
	display: grid;
	gap: 10px;
	max-height: 260px;
	overflow: auto;
}
.thirdpartynotify-dialog-user {
	display: flex;
	gap: 10px;
	align-items: center;
	min-height: 38px;
	padding: 9px 11px;
	border: 1px solid #d8dee8;
	border-radius: 7px;
	background: #f9fafb;
	color: #111827;
	cursor: pointer;
	transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
}
.thirdpartynotify-dialog-user:hover {
	border-color: #9db4d8;
	background: #f3f7fc;
}
.thirdpartynotify-dialog-user input {
	width: 16px;
	height: 16px;
	margin: 0;
	accent-color: #0b65c2;
}
.thirdpartynotify-dialog-user span {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.thirdpartynotify-dialog-select-all {
	margin-bottom: 10px;
	background: #eef5ff;
	border-color: #b8cff0;
	font-weight: 600;
}
.thirdpartynotify-dialog-actions {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 18px;
	padding-top: 14px;
	border-top: 1px solid #edf0f4;
}
.thirdpartynotify-dialog-actions .button {
	min-width: 92px;
	height: 34px;
	border-radius: 6px;
	font-weight: 700;
}
.thirdpartynotify-dialog-confirm {
	color: #fff;
	background: #0b65c2;
	border-color: #0b65c2;
}
.planity-kanban-delete {
	margin-left: 8px;
	border: 0;
	background: transparent;
	color: #c62828;
	cursor: pointer;
}
.planity-kanban-delete:disabled {
	opacity: .5;
	cursor: default;
}
.planity-delete-confirm {
	width: min(300px, calc(100vw - 32px));
	padding: 14px;
}
.planity-delete-confirm .thirdpartynotify-dialog-title {
	margin-bottom: 12px;
	font-weight: 600;
}
.planity-delete-confirm .planity-delete-ok {
	color: #fff;
	background: #c62828;
	border-color: #c62828;
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
