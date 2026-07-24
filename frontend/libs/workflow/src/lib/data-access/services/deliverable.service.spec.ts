import { TestBed } from '@angular/core/testing';

import { DeliverableService } from './deliverable.service';

describe('DeliverableService', () => {
  let service: DeliverableService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(DeliverableService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
