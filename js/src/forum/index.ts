import app from 'flarum/forum/app';
import Extend from 'flarum/common/extenders';
import Discussion from 'flarum/common/models/Discussion';
import gallery from './gallery';
import work from './work';

/*
 * Studio draws two things differently from core: the discussion list, as a
 * gallery of each discussion's own first picture, and a discussion's opening
 * post, as the work (its pictures on a stage, a card for its creator). Pages,
 * routes and the stream are core's own, so every extension that adds to them
 * keeps working.
 *
 * Under ernestdefoe/wardrobe this file is only in Studio's own bundle: a
 * visitor on another theme never runs it.
 */
export const extend = [
  new Extend.Model(Discussion).attribute<string | null>('studioCover').attribute<number>('studioRatio').attribute<number>('studioImages'),
];

app.initializers.add('ernestdefoe-studio', () => {
  gallery();
  work();
});
