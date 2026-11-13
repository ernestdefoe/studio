import app from 'flarum/admin/app';
import Admin from 'flarum/common/extenders/Admin';

/**
 * Studio's settings page. Each setting names its reader.
 *
 * 🚨 Exported from admin/index.ts only: this imports `flarum/admin/app`,
 * which does not exist on the forum.
 */
const K = (key: string) => `ernestdefoe-studio.${key}`;
const t = (key: string) => app.translator.trans(`ernestdefoe-studio.admin.${key}`);

export default [
  new Admin()
    // less/tokens.less
    .setting(() => ({ setting: K('accent'), type: 'color-preview', label: t('accent_label'), help: t('accent_help'), default: '#C2341A' }))
    .setting(() => ({ setting: K('ground'), type: 'color-preview', label: t('ground_label'), help: t('ground_help'), default: '#F3F2EF' }))
    // extend.php: the fonts and their preloads
    .setting(() => ({ setting: K('fonts'), type: 'boolean', label: t('fonts_label'), help: t('fonts_help') }))
    // forum/util railMode
    .setting(() => ({ setting: K('rail'), type: 'boolean', label: t('rail_label'), help: t('rail_help') }))
    // forum/gallery: the chips and the switch
    .setting(() => ({ setting: K('chips'), type: 'boolean', label: t('chips_label'), help: t('chips_help') }))
    .setting(() => ({ setting: K('toggle'), type: 'boolean', label: t('toggle_label'), help: t('toggle_help') }))
    // forum/gallery/Card
    .setting(() => ({ setting: K('counts'), type: 'boolean', label: t('counts_label'), help: t('counts_help') })),
];
