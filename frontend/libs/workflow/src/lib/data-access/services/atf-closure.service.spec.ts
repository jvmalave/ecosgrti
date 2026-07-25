import { TestBed } from '@angular/core/testing';

import { AtfClosureService } from './atf-closure.service';

describe('AtfClosureService', () => {
  let service: AtfClosureService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(AtfClosureService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
