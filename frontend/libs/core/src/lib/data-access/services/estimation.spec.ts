import { TestBed } from '@angular/core/testing';

import { Estimation } from './estimation';

describe('Estimation', () => {
  let service: Estimation;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Estimation);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
