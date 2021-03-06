class AddClassOnWrapDirective {
  constructor($timeout) {
    this.restrict = 'A';
    this.scope = {
      'wrapClass': '@addClassOnWrap'
    };
    this.link = this.link.bind(this);
    this.$timeout = $timeout;
    this.addWrapClass = this.addWrapClass.bind(this);
  }

  link(scope, element, attrs) {
    this.$timeout(() => {
      this.origLineHeight = parseInt(element.css('line-height'), 10);
      this.addWrapClass(element, scope.wrapClass);
    }, 200);

    angular.element(window).on('resize', () => {
      this.addWrapClass(element, scope.wrapClass);
    });
  }

  addWrapClass(element, wrapClass) {
    if (element.height() > this.origLineHeight) {
      element.addClass(wrapClass);
    }
  }
}

// @ngInject
export default ($timeout) => {
  return new AddClassOnWrapDirective($timeout);
};
